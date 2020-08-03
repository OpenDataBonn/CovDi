<?php
/**
URL-Ziel zum Suchen von bereits erfasst Kontaktpersonen
*/
include "../../includes.php";

$oData = new \base\OData;        
//var_dump($_GET);

$response = $oData->searchKontaktperson($_GET['term']);
//var_dump($response);

echo json_encode($response);
?>