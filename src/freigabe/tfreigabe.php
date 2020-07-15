<?php
/**
URL-Ziel für das speichern der Freigaben von Quaratänen
*/

include "../../includes.php";

$oData = new \base\OData;        
$response = $oData->saveTFreigaben($_POST);
//var_dump($response);
?>