<?php
/**
Url-Endziel zum anfordern der PIN über den OData-Service
*/
include "../../includes.php";

$oData = new \base\OData;
$email = $_POST['email'];

$login = $oData->getPin($email);

?>