<?php
/**
URL-Ziel für das speichern der Freigaben von Quaratänen
*/

include "../../includes.php";

$oData = new \base\OData;        
$response = $oData->saveQFreigaben($_POST);
?>