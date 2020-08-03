<?php
namespace base;

/*
Klasse zur Steuerung der Anzeige, 
verwaltet die Parameter zur Anzeigensteuerung inklsuive der Rechte zur Anzeige bestimmer Blöcke oder Bearbeitungsmöglichkeiten.
*/

class View {
    //vorhalten der Rechte des aktuellen Users
    private $rights = '';
    //Schalter ob der User bereits eingelogged ist
    private $loggedIn = false;
    //Userdaten
    private $user = false;
    //Basis-Anzeigetyp
    private $type = "main";
    //Suchparameter für die Listansicht
    private $search = array();
    
    function __construct() {
        //Basiseinstellungen aus der config.php
        global $basics;     
        //Check ob der User eingeloggt ist
        if (isset($_SESSION[$basics['session']]['user'])){
            $this->loggedIn = true;
            
            $this->rights = $_SESSION[$basics['session']]['user']['rechte'];            
            $this->user = $_SESSION[$basics['session']]['user'];            
            //var_dump($this->user);
        }
        
        //Anzeigetyp
        if (isset($_GET["type"])) $this->type = $_GET["type"];
        //Prüfung ob auch geschlossene Fälle durchsucht werden sollen
        if (isset($_GET["closed"])) $this->search["closed"] = true;
        else $this->search["closed"] = false;
        
        //Suchparameter einlesen / im öffentlichen Einsatz sollte hier noch eine CrossSiteScripting vorsorge eingebaut werden
        if (isset($_GET["search"])) $this->search["search"] = $_GET["search"];
        else $this->search["search"] = "";
        
        //Sortierung für die Listenansicht einlesen
        if (isset($_GET["sorting"])) $this->search["sorting"] = $_GET["sorting"];
        else $this->search["sorting"] = "LID";
        
    }
    
    //Basisfunktion der Klasse
    function getMain(){
        $main = "";
        //Überprüfung der Basis-Rechte der eingeloggten Person
        //read => Nur lesender Zugriff von Fällen
        //edit => Bearbeitung von Grunddaten der Fälle
        //doc => Anweisung von Maßnahmen in Fällen erlaubt
        //erweiterte Rechte wie das Freigeben von Quarantänen oder die Anzeige von Auswertungen werden über zusätzliche 
        //Berechtigungen vergeben
        
        switch($this->getMainRight()){
            case 'doc':                        
            case 'edit':
                    if (($this->type == "add")) $main = $this->getAdd();
            case 'read':
                    switch ($this->type){
                        //Listenansicht, alle oder neu erfasste Meldungen
                        case "all":
                        case "new":
                            if (isset($_GET["tpage"])) $page = $_GET["tpage"];
                            else $page = 1;
                            $main = $this->getTablePage($page, $this->search, $this->type);
                            break;
                        //Endenden Quarantänen
                        case "qend":
                            $main = $this->getEndingQs();
                            break;
                        //Abstriche ohne Rückmeldung
                        case "anor":
                            $main = $this->getAOhneRueck();
                            break;
                        //Einzelansicht des Falls
                        case "single":   
                            $main = $this->getSingle($_GET["id"]);
                            break;
                        //Basisseite mit Login
                        case "main":
                            $main = file_get_contents("templates/start_loggedin.html");
                            break;
                        //Auswertungen
                        case "auswertungen":
                            $main = $this->getAuswertungen();
                            break;
                        //Freigaben von Quarantänen
                        case "freigabeq":
                            $main = $this->getFreigabeQ();
                            break;
                        //Freigaben von Tätigkeitsverboten
                        case "freigabet":
                            $main = $this->getFreigabeT();
                            break;
                        //Druckansicht
                        case "print":
                            $main = $this->getPrint($_GET["printType"], $_GET["id"]);
                    }
                break;
            //Standard-Startseite oder Hinweis, das ein Login benötigt wird
            default:
                if ($this->type != "main") $main = file_get_contents("templates/need_login.html");
                else {
                    $template = file_get_contents("templates/start.html");
                    $main = $template;
                }
                break;
        }
        return $main;
    }
    
    //Aufbau der zentralen Navigation
    function getNav(){
        $nav_add = '';
        $nav_add_qfreigabe = '';
        $nav_add_tfreigabe = '';
        $nav_add_new = '';
        //Die Navigationsleite wird erst angezeigt, wenn ein Nutzer sich eingeloggt hat
        if ($this->loggedIn){
            //Anzeige Logout-Button
            $login = file_get_contents("templates/logout.html");
            $login = str_replace('###NACHNAME###',$this->user['nachname'],$login);
            $login = str_replace('###VORNAME###',$this->user['vorname'],$login);   
            //Auswertungen
            if (in_array('aZahlen', $this->rights)){
                $nav_add .= '<a class="dropdown-item" href="?type=auswertungen">Auswertungen</a>';                
            }
            //Freigaben von Quarantänen oder Tätigkeitsverboten
            if (in_array('freig', $this->rights)){
                $nav_add_qfreigabe = '<a class="dropdown-item" href="?type=freigabeq">Freigabe Quarantänen</a>';
                $nav_add_tfreigabe = '<a class="dropdown-item" href="?type=freigabet">Freigabe Tätigkeitsverbote</a>';
            }
            //Erfassung neuer Fälle mit CovDi
            if (in_array('edit', $this->rights) || in_array('doc', $this->rights)){
                $nav_add_new = '<a class="dropdown-item" href="?type=add">Neue Meldung erfassen</a>';
            }
            $nav = file_get_contents("templates/nav.html");
        } else {
            $login = file_get_contents("templates/login.html");
            $nav = file_get_contents("templates/nav_nomenu.html");
        }

        $nav = str_replace("###".strtoupper($this->type)."###",'active',$nav);
        $nav = str_replace("###LOGIN###",$login,$nav);
        $nav = str_replace("###NAV_ADD_AUSWERTUNGEN###", $nav_add, $nav);
        $nav = str_replace("###NAV_ADD_QFREIGABE###", $nav_add_qfreigabe, $nav);
        $nav = str_replace("###NAV_ADD_TFREIGABE###", $nav_add_tfreigabe, $nav);
        $nav = str_replace("###NAV_ADD_NEWM###", $nav_add_new, $nav);

        return $nav; 
    }
    
    //Duckansicht anzeigen, das Rahmentemplate wird hier nicht geladen
    function getPrint($printType, $id){
        $template = "";
        $oData = new\base\OData;
        
        switch($printType){
            //Deckblatt für Papiertakte
            case "frontpage":
                $template = file_get_contents("templates/print/front_page.html");
                $template = $oData->getSingleFrontPage($id, $template);
                break;                
        }
        
        return $template;
    }
    
    //Anzeige von Auswertungen
    function getAuswertungen(){
        $template = file_get_contents("templates/auswertungen/main.html");
        $oData = new\base\OData;
        if ($this->loggedIn && in_array('aZahlen',$this->rights)){
            $base = file_get_contents("templates/auswertungen/tabs/base.html");
            $base = $oData->getBaseReport($base);
            $graph = file_get_contents("templates/auswertungen/tabs/graph.html");
            $template = str_replace("###base_tab###", $base, $template);
            $template = str_replace("###graph_tab###", $graph, $template);            
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        return $template;
    }
    
    //Anzeige Freigaben von Quarantänen
    function getFreigabeQ(){
        $template = file_get_contents("templates/freigaben/quarantaene_freigaben.html");
        $row_template = file_get_contents("templates/freigaben/q_freigabe_row.html");
        $oData = new\base\OData;
        $rows = "";
        if ($this->loggedIn && in_array("freig", $this->rights)){
            $rows = $oData->getQFreigaben($row_template);
            $template = str_replace("###ROWS###", $rows, $template);     
            $template = str_replace("###UID###", $this->user['uid'], $template);
            $template = str_replace("###USER###", $this->user['nachname'].', '.$this->user['vorname'],$template);
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        return $template;
    }
    
    //Anzeige Freigaben von Tätigkeitsverboten
    function getFreigabeT(){
        $template = file_get_contents("templates/freigaben/tatverbote_freigaben.html");
        $row_template = file_get_contents("templates/freigaben/t_freigabe_row.html");
        $oData = new\base\OData;
        $rows = "";
        if ($this->loggedIn && in_array("freig", $this->rights)){
            $rows = $oData->getTFreigaben($row_template);
            $template = str_replace("###ROWS###", $rows, $template);     
            $template = str_replace("###UID###", $this->user['uid'], $template);
            $template = str_replace("###USER###", $this->user['nachname'].', '.$this->user['vorname'],$template);
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        return $template;
    }
    
    //Anzeige Formuar zu Erfassung von Fällen über die Oberfläche von CovDi
    function getAdd(){
        global $basics;
        $template = file_get_contents("templates/single/new.html");  
        $head = file_get_contents("templates/single/head_new.html");
        $base_tab = file_get_contents("templates/single/tabs/basetab_edit.html");
        $contact_tab = file_get_contents("templates/single/tabs/contacttab_edit.html");
        $health_tab = file_get_contents("templates/single/tabs/healthtab_edit.html");    
        $actions_tab = file_get_contents("templates/single/tabs/actionstab_new.html");    
        $foot = file_get_contents("templates/single/foot_edit.html");

        $template = str_replace('###head###',$head,$template);

        $template = str_replace('###base_tab###',$base_tab,$template);
        $template = str_replace('###contact_tab###',$contact_tab,$template);
        $template = str_replace('###health_tab###',$health_tab,$template);
        $template = str_replace('###actions_tab###',$actions_tab,$template);

        $template = str_replace('###foot###',$foot,$template);
        $template = str_replace('###UID###',$this->user['uid'],$template);
        $today =  new \DateTime();
        $template = str_replace('###ACT_DATUM###',$today->format('d.m.Y'),$template);
        if (isset($_SESSION[$basics['session']]['user'])) {
            $template = str_replace('###ACT_USER###',$_SESSION[$basics['session']]['user']['nachname'].', '.$_SESSION[$basics['session']]['user']['vorname'],$template);
        }
        
        $add = $this->eraseMarkers($template);
        return $add;
    }
    
    //Anzeige Einzelansicht Fall
    function getSingle($id){
        $template = file_get_contents("templates/single/single.html");  
        $oData = new \base\OData;
        if ($this->loggedIn){
            $head_add = '';
            $basetab_add = '';
            $healthtab_add = '';
            $actionstab_add = '';
            $contacttab_add = '';
            $notestab_add= '';
            $docstab_add= '';
            //if ($oData->isOldData($id)) $actionstab_add = '_old';
            $actionstab_abstriche_add = '';
            $actionstab_quarantaenen_add = '';
            $actionstab_tatverbote_add = '';            
            $actionstab_bluttests_add = '';
            $contacttab_contact_add = '';
            $foot_add = '';
            if ($oData->isLocked($id)) $this->rights[] = 'locked';

            //Rechteabhängige Anzeige von Bearbeitungsoberflächen
            //Der Aufbau der Template bezeichnungen:
            //Read-Only: Templatename
            //Edit: Templatename_edit
            switch($this->getMainRight()){
                case 'doc':
                    $actionstab_add .= '_edit';
                    $actionstab_abstriche_add .= '_edit';
                    $actionstab_quarantaenen_add .= '_edit';
                    $actionstab_tatverbote_add .= '_edit';                    
                    $actionstab_bluttests_add .= '_edit';    
                case 'edit':
                    $head_add .= '_edit';
                    $basetab_add .= '_edit';
                    $contacttab_add .= '_edit';
                    $healthtab_add .= '_edit';
                    $foot_add .= '_edit';                    
                    $notestab_add .= '_edit';
                    $contacttab_contact_add = '_edit';
                    break;
                default:
                    break;                
            }  
            $head = file_get_contents("templates/single/head".$head_add.".html");
            $base_tab = file_get_contents("templates/single/tabs/basetab".$basetab_add.".html");
            $contact_tab = file_get_contents("templates/single/tabs/contacttab".$contacttab_add.".html");
            $contact_tab_kontakte = file_get_contents("templates/blocks/contact".$contacttab_contact_add.".html");
            $health_tab = file_get_contents("templates/single/tabs/healthtab".$healthtab_add.".html");    
            $actions_tab = file_get_contents("templates/single/tabs/actionstab".$actionstab_add.".html");    
            $actions_tab_abstriche = file_get_contents("templates/blocks/abstrich".$actionstab_abstriche_add.".html");
            $actions_tab_quarantaenen = $this->getSpecialParts(file_get_contents("templates/blocks/quarantaene".$actionstab_quarantaenen_add.".html"),'single_quar');
            $actions_tab_tatverbote = file_get_contents("templates/blocks/tatverbot".$actionstab_tatverbote_add.".html");
            $actions_tab_bluttests = file_get_contents("templates/blocks/bluttest".$actionstab_bluttests_add.".html");
            $notes_tab = file_get_contents("templates/single/tabs/notestab".$notestab_add.".html");    
            $docs_tab = file_get_contents("templates/single/tabs/docstab".$docstab_add.".html");    
            $foot = file_get_contents("templates/single/foot".$foot_add.".html");
            
            $template = str_replace('###head###',$head,$template);
        
            $template = str_replace('###base_tab###',$base_tab,$template);
            $template = str_replace('###contact_tab###',$contact_tab,$template);
            $template = str_replace('###health_tab###',$health_tab,$template);
            $template = str_replace('###actions_tab###',$actions_tab,$template);
            $template = str_replace('###notes_tab###',$notes_tab,$template);
            $template = str_replace('###docs_tab###',$docs_tab,$template);

            $template = str_replace('###foot###',$foot,$template);
            $template = str_replace('###UID###',$this->user['uid'],$template);
            
            $template = $this->getSpecialParts($template, 'single_main');

            $single = $oData->getSingle($id, $template, $actions_tab_abstriche, $actions_tab_quarantaenen, $actions_tab_tatverbote, $actions_tab_bluttests, $contact_tab_kontakte);

            return $single;
        } else {
            return file_get_contents("templates/need_login.html");
        }            
    }
    
    //Mit Sonderrechten wird die Anzeige und die Bearbeitbarkeit von Unterlementen gesteuert
    function getSpecialParts($template, $part){
        switch ($part){
            case 'single_main':
                //Survnet-Eintragung
                if ($this->getMainRight() != 'read' && in_array('survnet', $this->rights)){
                    $survnet = file_get_contents("templates/blocks/survnet_edit.html");
                    $survnet_kennung = file_get_contents("templates/blocks/survnet_kennung_edit.html");
                } else {
                    $survnet = file_get_contents("templates/blocks/survnet.html");
                    $survnet_kennung = file_get_contents("templates/blocks/survnet_kennung.html");
                }
                $template = str_replace('###SURVNET###', $survnet, $template);
                $template = str_replace('###SURVNET_KENNUNG###', $survnet_kennung, $template);
                break;
            case 'single_quar':
                //Bearbeitung der Notizen zur OV-Benachrichtigung
                if (/*$this->getMainRight() != 'read' && */in_array('ovEdit', $this->rights)){
                    $ovEdit = file_get_contents("templates/blocks/quarantaene_ov_edit.html");
                } else {
                    $ovEdit = file_get_contents("templates/blocks/quarantaene_ov.html");
                }
                $template = str_replace('###OV_INFOS###', $ovEdit, $template);
                break;
        }
        
        return $template;
    }

    //Steuerung der Tabellen-Anzeige
    function getTablePage($page, $search, $type = "all"){
        $template = file_get_contents("templates/".$type.".html");
        $pageSize = 15;
        $oData = new \base\OData;
        $meldungen = $oData->getMeldungenTableRows($page, $pageSize, $search, $type);
        //var_dump($meldungen);
        $table = file_get_contents("templates/tables/table.html");
        $table = str_replace('###ROWS###',$meldungen, $table);
        $totalCount = $oData->getMeldungenCount($search,$type)->body;
        if ($totalCount > 0) $table = str_replace('###PAGINATION###',$this->getTablePagination($page, $pageSize, $totalCount, $search, $type),$table);    
        else  $table = str_replace('###PAGINATION###','',$table);    

        $template = str_replace("###table###",$table,$template);
        $template = str_replace("###search###",$search['search'],$template);
        $template = str_replace("###sorting###",$search['sorting'],$template);
        if ($search['closed']) $template = str_replace("###closed###",'checked="checked"',$template);
        return $template;
    }

    //Tabellen-Navigation
    function getTablePagination($page, $pageSize, $totalCount, $search, $type = "all"){
        $pageCount = ceil($totalCount / $pageSize);
        $adder = "";
        if ($search['closed']) $adder = "&closed=1";
        if ($search['search']) $adder .= "&search=".$search['search'];
        if ($search['sorting']) $adder .= "&sorting=".$search['sorting'];
        if ($pageCount == 1) return "";
        $pagination = file_get_contents("templates/tables/table_pagination.html");
        $items = "";
        if ($page != 1){
            $items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=1$adder\">Anfang</a></li>";
            $items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page-1)."$adder\">Vorherige</a></li>";
        }
        if (($page - 4)>=1)$items = $items."<li class=\"page-item\"><a class=\"page-link\" >...</li>";
        if (($page - 3)>=1)$items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page-3)."$adder\">".strval($page-3)."</a></li>";
        if (($page - 2)>=1)$items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page-2)."$adder\">".strval($page-2)."</a></li>";
        if (($page - 1)>=1)$items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page-1)."$adder\">".strval($page-1)."</a></li>";
        $items = $items."<li class=\"page-item active\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page)."$adder\">".strval($page)."</a></li>";
        if (($page + 1)<=$pageCount)$items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page+1)."$adder\">".strval($page+1)."</a></li>";
        if (($page + 2)<=$pageCount)$items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page+2)."$adder\">".strval($page+2)."</a></li>";
        if (($page + 3)<=$pageCount)$items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page+3)."$adder\">".strval($page+3)."</a></li>";
        if (($page + 4)<=$pageCount)$items = $items."<li class=\"page-item\"><a class=\"page-link\" >...</li>";
        if ($page != $pageCount && $pageCount > 1){
            $items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($page+1)."$adder\">Nächste</a></li>";
            $items = $items."<li class=\"page-item\"><a class=\"page-link\" href=\"?type=$type&tpage=".strval($pageCount)."$adder\">Ende</a></li>";
        }
        $pagination = str_replace("###items###",$items,$pagination);
        $pagination = str_replace("###pagecount###",$pageCount,$pagination);

        return $pagination;
    }

    //Anzeige auslaufende Quarantänen
    function getEndingQs(){
        $oData = new \base\OData;    
        $template = file_get_contents("templates/tables/qus_ending.html");
        $days = 14;
        $template = str_replace('###DAYS###',$days,$template);
        $rows = $oData->getEndingQus($days);
        $template = str_replace('###ROWS###',$rows,$template);

        return $template;
    }    
    
    //Anzeige Abstriche ohne Rückmeldung
    function getAOhneRueck(){
        $oData = new \base\OData;    
        $template = file_get_contents("templates/tables/as_orueck.html");
        $rows = $oData->getAOhneRueck();
        $template = str_replace('###ROWS###',$rows,$template);

        return $template;
    }    

    //Einbinden der Javascripte nach Anzeigetyp
    function getScript(){
        $script = "";
        switch ($this->type){
            case "single":   
            case "add":
                if ($this->loggedIn){
                    switch($this->getMainRight()){
                        case 'doc':
                            $script = "<script src=\"js/single_doc.js\"></script>";     
                        case 'edit':
                            $script = $script."<script src=\"js/single_edit.js\"></script>";    
                            break;
                    }
                }
                break;
            case "auswertungen":
                $script.= "<script src=\"js/html2canvas.min.js\"></script>";
                $script.= "<script src=\"js/auswertungen.js\"></script>";
                break;
            case "freigabeq":
                $script.= "<script src=\"js/qfreigaben.js\"></script>";
                break;
            case "freigabet":
                $script.= "<script src=\"js/tfreigaben.js\"></script>";
                break;
            case "all":
            case "new":
                $script.= "<script src=\"js/lists.js\"></script>";
                break;
        }

        return $script;
    }
    
    //Basisrechte ermitteln
    function getMainRight(){
        if (is_array($this->rights)){
            //wenn der Fall abgeschlossen ist, wird immer nur lese-Rechte zurück gegeben
            if (in_array('locked', $this->rights)) return 'read';
            //Falls Basis-Rechte mehrfach vergeben wurden, sollte immer nur das höchste Recht zurück gegeben werden
            if (in_array('doc', $this->rights)) return 'doc';
            if (in_array('edit', $this->rights)) return 'edit';
            return 'read';    
        }
        
    }
    
    //Marker aus einem Template entfernen (Marker: ###___###)
    function eraseMarkers($template){
        // preg_replace('/CROPSTART[\s\S]+?CROPEND/', '', $string);
        $template = preg_replace('/###[\s\S]+?###/','',$template);
        return $template;
    }
}

?>