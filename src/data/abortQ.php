<?php
/**
URL-Ziel zum Abbrechen von Quarantänen. Bei Fehler kommt eine ensprechende Meldung, damit der Nutzer merkt das was schieff gelaufen ist und den Admin kontaktieren kann.
Hilft vor allem bei der Fehlersuche
*/
include "../../includes.php";

$oData = new \base\OData;        

$response = $oData->abortQuarantaene($_POST['id'],$_POST['date'],$_POST['durch']);
//var_dump($response);
if ($response->hasErrors()){
    echo false;
} else {
    echo true;
}
?>