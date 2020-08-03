<?php
/**
URL-Ziel zum Suchen von bereits erfasst Kontaktpersonen
*/
include "../../includes.php";

$oData = new \base\OData;        
//var_dump($_GET);

$response = $oData->connectKontaktperson($_POST);
var_dump($response);
if ($response->hasErrors()){
    echo false;
} else {
    echo true;
}
?>