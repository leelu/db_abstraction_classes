<?php

include_once("MSAccessDataLIB.php");
$DSN = str_replace("/", "\\", $_SERVER["DOCUMENT_ROOT"]) . "\\test\\test.mdb";
if(!file_exists($DSN))
{
    print_r("cannot connect to db, invalid mdb");
    exit;
}

$DBInstance = MSAccessDataLIB::Get($DSN);
print('<pre>');
print_r($DBInstance->FetchList("SELECT * FROM TestUsers WHERE UserId IN (1001, 1002)", "UserName", "UserId"));

?>