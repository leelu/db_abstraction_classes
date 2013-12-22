<?php
/**
* $Id$
*
* Copyright (c) 2013, Leeladharan M P Achar.  All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
*/

/**
* MS Access DB Wrapper PHP class
*
* @link http://www.leeladharan.com/ms-access-database-abstraction-class-in-php
* @version 0.1
*/
class MSAccessDataLIB
{

	private $DBName 		= '';		
	private $DBHandle		= null;
	private $Statement		= null;
	private static $ActiveConnections	= array();


	/**
	*	@brief Constructor
	*
	*	@param mixed name of the db as defined in Config/Data.php, OR an array containing the appropriate db settings.
	*/
	private function __construct($DSN)
	{
		$this->DBName = $DSN;
		$this->Connect();
	}

	/**
	*	@brief Destructor - must close any open connections
	*
	*/
	public function __destruct()
	{
		if($this->DBHandle)
		{
			// Destructors might be called first. Weirdness.
			return (@odbc_close($this->DBHandle));
		}
		$this->DBHandle = null;
		$this->DBName = array();
	}


	private function Connect()
	{
		if(is_array($this->DBName))
		{
			$DB = $this->DBName['DSN'];
		}
		else
		{
			$DB = $this->DBName;
		}

		
		try
		{
			//please visit php.net for odbc_connect params
			$this->DBHandle = odbc_connect("DRIVER={Microsoft Access Driver (*.mdb)}; DBQ=$DB", "ADODB.Connection", "", SQL_CUR_USE_ODBC);
		}
		catch(Exception $e)
		{
			if(!$this->DBHandle)
			{
				throw new DataException(DataException::CONNECTION_ERROR,$e->getMessage());
			}
		}
	}

	/**
	*	@brief Get a DB Instance;
	*
	*	@param string containing db name.
	*/
	public static function Get($DatabaseName)
	{
		// Connect using Database info array
		if(is_array($DatabaseName))
		{
			$DataArray = $DatabaseName;
			if(isset($DataArray['DSN']))
			{
				$DatabaseName = $DataArray['DSN'];
			}
			else // take care of wrong params
			{
				throw new DataException(DataException::INVALID_KEY, implode(',',$DatabaseName));
			}
			if(!isset(self::$ActiveConnections[$DatabaseName]))
			{
				self::$ActiveConnections[$DatabaseName] = new self($DataArray);
			}
		}
		else // Connect using Database name (Uses GetDBInfo to get the info array)
		{
			if(!isset(self::$ActiveConnections[$DatabaseName]))
			{
				self::$ActiveConnections[$DatabaseName] = new self($DatabaseName);
			}
		}

		return self::$ActiveConnections[$DatabaseName];
	}

	/**
	*	@brief Get a DB Handle;
	*
	*	
	*/
	public function GetDBHandle()
	{
		return $this->DBHandle;
	}

	/**
	* @brief Fetches a list of items
	*
	* @param string
	* @param string The column to extract (null = default to first column)
	* @param string The key to index the array on (null = use natural ordering)
	* @return array
	*/
	public function FetchList($Query, $Column = null, $Key = null)
	{
		$Results = array();
		if("" == $Query)
		{
			return $Results;
		}

		try
		{
			$DBResource = odbc_exec($this->DBHandle, $Query);
			//$Return = array();
			$i=1;
			while($Row=odbc_fetch_array($DBResource,$i)) 
			{
				$TempVal = null; $TempKey = null;
				$TempRow = array_values($Row);
				$TempVal = (($Column) && isset($Row[$Column])) ? $Row[$Column] : $TempRow[0];
				$TempKey = (($Key) && isset($Row[$Key])) ? $Row[$Key] : ($i-1);
				$Results[$TempKey] = $TempVal;
				$i++;
			} 
		} 
		catch(Exception $e)
		{
			throw new DataException(DataException::BAD_QUERY,$e->getMessage() . ", Query => " . $Query);
		}

		return $Results;
	}

	/**
	* @brief Performs a query and returns all results as an non-assoc array.
	*
	* @param		string	the query string
	* @param		string The key to index the array on (null = use natural ordering)
	* @return 		array 	returns associative arrays.
	*/
	public function FetchAssoc($Query, $Key=null)
	{
		$Results = array();
		if("" == $Query)
		{
			return $Results;
		}

		try
		{
			$DBResource = odbc_exec($this->DBHandle, $Query);
			$i=1;
			while($Row=odbc_fetch_array($DBResource,$i)) 
			{ 
				if($Key && isset($Row[$Key]))
				{
					$Results[$Row[$Key]][] = $Row;
				}
				else
				{
					$Results[count($Results)][] = $Row;
				}
				$i++;
			} 
		} 
		catch(Exception $e)
		{
			throw new DataException(DataException::BAD_QUERY,$e->getMessage() . ", Query => " . $Query);
		}

		return $Results;
	}

	/**
	* @brief Performs a query and returns all results as an non-assoc array.
	*
	* @param		string	the query string
	* @return 	array 	returns non-associative arrays.
	*/
	public function FetchArray($Query)
	{
		$Results = array();
		if("" == $Query)
		{
			return $Results;
		}

		try
		{
			$DBResource = odbc_exec($this->DBHandle, $Query);
			//$Return = array();
			$i=1;
			while($Row=odbc_fetch_array($DBResource,$i)) 
			{ 
				$Results[count($Results)] = $Row;
				$i++;
			} 
		} 
		catch(Exception $e)
		{
			throw new DataException(DataException::BAD_QUERY,$e->getMessage() . ", Query => " . $Query);
		}

		return $Results;
	}

	/**
	*	@brief Performs a query and returns the first column of the first row.
	*
	*	@param string the query string
	*	@return mixed
	*/
	public function FetchVal($Query)
	{
		$Result = null;
		if("" == $Query)
		{
			return $Result;
		}

		try
		{
			$DBResource = odbc_exec($this->DBHandle, $Query);
			//$Return = array();
			
			$Row=array_values(odbc_fetch_array($DBResource,1));
			$Result = $Row[0];
			
		} 
		catch(Exception $e)
		{
			throw new DataException(DataException::BAD_QUERY,$e->getMessage() . ", Query => " . $Query);
		}

		return $Result;
	}

	/**
	*	@brief Performs a query and returns the first result matching.
	*
	*	@param string the query string
	*	@return array
	*/
	public function FetchFirst($Query)
	{
		$Result = array();
		if("" == $Query)
		{
			return $Result;
		}

		try
		{
			$DBResource = odbc_exec($this->DBHandle, $Query);
			$Result = odbc_fetch_array($DBResource,1);
		} 
		catch(Exception $e)
		{
			throw new DataException(DataException::BAD_QUERY,$e->getMessage() . ", Query => " . $Query);
		}

		return $Result;
	}

	
	public function Update()
	{
	}

	
	public function Insert()
	{
	}

	
	/**
	*	@brief Performs a delete query.
	*
	*	@param string the query string
	*	@return 
	*/
	public function Delete($Query)
	{
		if("" == $Query)
		{
			return;
		}
		try
		{
			odbc_exec($this->DBHandle, $Query);				
		} 
		catch(Exception $e)
		{
			throw new DataException(DataException::BAD_QUERY,$e->getMessage() . ", Query => " . $Query);
		}

		return true;
	}

}  


/**
 *	@brief Exceptions thrown by Data class.
 *
 */
class DataException extends Exception
{
	const UNKNOWN			= 0;
	const CONNECTION_EXISTS	= 1;
	const CONNECTION_ERROR	= 2;
	const NO_CONFIGURATION	= 3;
	const INVALID_KEY		= 4;
	const BAD_QUERY			= 5;

	public function __construct($Category = 0,$Message = '')
	{
		switch($Category)
		{
			case self::CONNECTION_EXISTS:
				$Message = 'A connection to '.$Message.' is already active';
			break;
			case self::CONNECTION_ERROR:
				$Message = 'Connection error: '.$Message;
			break;
			case self::NO_CONFIGURATION:
				$Message = 'No configuration for '.$Message;
			break;
			case self::CONNECTION_ERROR:
				$Message = 'Connection error: '.$Message;
			break;
			case self::INVALID_KEY:
				$Message = 'Invalid key: '.$Message;
			break;
			case self::BAD_QUERY:
				$Message = 'Bad query: '.$Message;
			break;
			default:
				$Message = 'Unknown error: '.$Message;
			break;
		}
		parent::__construct($Message,$Category);
	}
};


?>