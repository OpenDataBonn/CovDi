<?php
/**
URL-Ziel zum öffnen und schliessen von Meldungen. Bei Fehler kommt eine ensprechende Meldung, damit der Nutzer merkt das was schieff gelaufen ist und den Admin kontaktieren kann.
Hilft vor allem bei der Fehlersuche
*/
include "../../includes.php";

$oData = new \base\OData;        

$response = $oData->openCloseMeldung($_POST['type'],$_POST['id']);
//var_dump($response);
if ($response->hasErrors()){
    echo false;
} else {
    echo true;
}
?>