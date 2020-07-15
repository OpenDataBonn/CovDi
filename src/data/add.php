<?php
/**
URL-Ziel zum speichern von Meldungen. Bei Fehler kommt eine ensprechende Meldung, damit der Nutzer merkt das was schieff gelaufen ist und den Admin kontaktieren kann.
Hilft vor allem bei der Fehlersuche
*/
include "../../includes.php";

$oData = new \base\OData;        

$response = $oData->addSingle($_POST);
$new_id = str_replace('Meldungen(','',$response->body->link['href']);
$new_id = str_replace(')','',$new_id);
//var_dump($new_id);
if ($response->hasErrors()){
    echo false;
} else {
    echo $new_id; 
}
?>