<?php
/**
Endziel für das Login. 
Mittels OData-Service wird die PIN geprüft. Bei Erfolg werden die User-Daten in die Session gesetzt, bei Misserfolg kommt eine Fehlermeldung.
*/
include "../../includes.php";

$oData = new \base\OData;
$pin = $_POST['pin'];

$login = $oData->checkPin($pin);
if ($login){
    $_SESSION[$basics['session']]['user'] = $login;
}

echo json_encode($login);
//echo $login;
?>