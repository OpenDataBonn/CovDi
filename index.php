<?php
include "includes.php";

//Hauptklasse, die alles verwaltet
$view = new \base\View();

//check ob der Rahmen angezeigt werden soll
//var_dump($basics);
if (!array_key_exists("type", $_GET) || !in_array($_GET["type"], $basics['blank'])){
    //Basis-Template der Seite
    $template = file_get_contents("templates/main.html");
    //die config.php verwaltet die Basis-Einstellungen wie Dev/Live Pfade und Links
    $template = str_replace("###page_title###", $basics['page_title'],$template);
    $template = str_replace("###page_title_color###", $basics['page_title_color'],$template);

    //Einsetzen der drei Bestandteile des Basis-Templates
    $homepage = str_replace("###nav###", $view->getNav(),$template);
    $homepage = str_replace("###main###", $view->getMain(),$homepage);
    $homepage = str_replace("###script###", $view->getScript(), $homepage);
} else {
    $homepage = $view->getMain();   
}

echo $homepage;
?>