<?php
/**
Logout ist einfach: Die Userdaten werden aus der Session entfernt
*/
include "../config.php";

unset($_SESSION[$basics['session']]['user']);
?>