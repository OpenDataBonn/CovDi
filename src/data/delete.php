<?php
/**
URL-Ziel zum löschen von Meldungen oder Unterdatensätzen. Bei Fehler kommt eine ensprechende Meldung, damit der Nutzer merkt das was schieff gelaufen ist und den Admin kontaktieren kann.
Hilft vor allem bei der Fehlersuche
*/
include "../../includes.php";

$oData = new \base\OData;        

$response = $oData->delete($_POST['type'],$_POST['id']);
//var_dump($response);
if ($response){
    echo true;
} else {
    echo false; 
}
?>