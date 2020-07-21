<?php
/**
URL-Ziel zum speichern von Meldungen. Bei Fehler kommt eine ensprechende Meldung, damit der Nutzer merkt das was schieff gelaufen ist und den Admin kontaktieren kann.
Hilft vor allem bei der Fehlersuche
*/
include "../../includes.php";

$oData = new \base\OData;        
//var_dump($_POST);

$response = $oData->setOvAnordnung($_POST);
//var_dump($response);
if ($response->hasErrors()){
    echo false;
} else {
    echo true;
}
?>