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
        global $basics;     
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
                    //Datensätze sperren: Sperren aufheben, wenn eine Tätigkeit stattgefunden hat.
                    //sollte eine Meldung aufgerufen werden, wird diese sofort wieder gesetzt
                    $oData = new\base\OData;
                    $oData->removeSperre();
                    if (($this->type == "add")) {
                        if (array_key_exists('copyid',$_GET)) $copy_id = $_GET['copyid'];
                            else $copy_id = false;
                        $main = $this->getAdd($copy_id);
                        break;
                    }
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
                        case "freigabeQ":
                            $main = $this->getFreigabeQ();
                            break;
                        //Freigaben von Tätigkeitsverboten
                        case "freigabeT":
                            $main = $this->getFreigabeT();
                            break;
                        //Druckansicht
                        case "print":
                            $main = $this->getPrint($_GET["printType"], $_GET["id"]);
                            break;
                        //ohne Survnet Einträge
                        case "svNetO":
                            $main = $this->getSvNetO();
                            break;
                        //Nutzerliste
                        case 'listUser':
                            $main = $this->getUserList();
                            break;
                        //Nutzerliste
                        case 'listUser':
                            $main = $this->getUserList();
                            break;
                        //Einsatzplanung 
                        case 'ePlanung':
                            if ($basics['ep']){
                                $main = $this->getEPlanung();
                                break;
                            }
                         case "freigabeR":
                            if ($basics['ep']){
                                $main = $this->getFreigabeR();
                                break;
                            }
                        //Importe
                        case 'import':
                            $main = $this->getImporte();
                            break;
                        default:
                            $template = file_get_contents("templates/start.html");
                            $main = $template;
                            break;
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
        global $basics;
        $marker = array();
        //Die Navigationsleite wird erst angezeigt, wenn ein Nutzer sich eingeloggt hat
        if ($this->loggedIn){
            //Anzeige Logout-Button
            $login = file_get_contents("templates/logout.html");
            $login_marker = array();
            $login_marker['###NACHNAME###'] = $this->user['nachname'];
            $login_marker['###VORNAME###'] = $this->user['vorname'];
            $marker['###LOGIN###'] = $this->replaceMarker($login, $login_marker);
            //Auswertungen
            if (in_array('aZahlen', $this->rights)){
                $marker['###NAV_ADD_AUSWERTUNGEN###'] = '<a class="dropdown-item" href="?type=auswertungen">Auswertungen</a>';                
            }
            //Freigaben von Quarantänen oder Tätigkeitsverboten
            if (in_array('freig', $this->rights)){
                $marker['###NAV_ADD_QFREIGABE###'] = '<div class="dropdown-divider"></div>';
                $marker['###NAV_ADD_QFREIGABE###'] .= '<a class="dropdown-item" href="?type=freigabeQ">Freigabe Quarantänen</a>';
                $marker['###NAV_ADD_TFREIGABE###'] = '<a class="dropdown-item" href="?type=freigabeT">Freigabe Tätigkeitsverbote</a>';
            }
            //Erfassung neuer Fälle mit CovDi
            if (in_array('edit', $this->rights) || in_array('doc', $this->rights)){
                $marker['###NAV_ADD_NEWM###'] = '<div class="dropdown-divider"></div>';
                $marker['###NAV_ADD_NEWM###'] .= '<a class="dropdown-item" href="?type=add">Neue Meldung erfassen</a>';
            }
            //Liste zur Survnet-Eintragung
            if (in_array('survnet', $this->rights)){
                $marker['###NAV_ADD_SURVNET###'] = '<div class="dropdown-divider"></div>';
                $marker['###NAV_ADD_SURVNET###'] .= '<a class="dropdown-item" href="?type=svNetO">SurvNet ohne Eintragung</a>';                
            }
            //Liste der Nutzer
            if (in_array('admUser', $this->rights)){
                $marker['###NAV_ADD_USER###'] = '<div class="dropdown-divider"></div>';
                $marker['###NAV_ADD_USER###'] .= '<a class="dropdown-item" href="?type=listUser">Nutzerliste</a>';                
            }
            //CSV-Importe
            //importMs => Massendatenimport Meldungen, evtl. später eventuell noch anderes
            if (in_array('importMs', $this->rights)){
                $marker['###NAV_ADD_IMPORT###'] = '<div class="dropdown-divider"></div>';
                $marker['###NAV_ADD_IMPORT###'] .= '<a class="dropdown-item" href="?type=import">Daten-Import</a>';                
            }
            //Einsatzplanung
            if ($basics['ep']){
                $marker['###NAV_ADD_EP###'] = '<a class="dropdown-item" href="?type=ePlanung">Einsatzplanung</a>';
                if (in_array('rosterFreig', $this->rights)){
                    $marker['###NAV_ADD_RFREIGABE###'] = '<a class="dropdown-item" href="?type=freigabeR">Freigabe Einsatzplanung</a>';
                }
                if (in_array('roster', $this->rights)){
                    $marker['###NAV_ADD_EPTOOL###'] = '<div class="dropdown-divider"></div>';
                    if ($basics['session'] == 'covdi'){                    
                        $marker['###NAV_ADD_EPTOOL###'] .= '<a class="dropdown-item" target="_blank" href="http://sv12566.intern.stadt-bn.de/gesundheitsamt/covdi-einsatzplanung/?type=roster">Tool: Einsatzplanung</a>';
                    } else {
                        $marker['###NAV_ADD_EPTOOL###'] .= '<a class="dropdown-item" target="_blank" href="http://sv12566.intern.stadt-bn.de/gesundheitsamt/covdi-einsatzplanung_test/?type=roster">Tool: Einsatzplanung</a>';
                    }
                }
            }
            $nav = file_get_contents("templates/nav.html");
        } else {
            $marker['###LOGIN###'] = file_get_contents("templates/login.html");
            $nav = file_get_contents("templates/nav_nomenu.html");
        }
        //var_dump($marker);
        $marker["###".strtoupper($this->type)."###"] = 'active';
        $nav = $this->replaceMarker($nav, $marker);

        return $nav; 
    }
    
    function getImporte(){
        $template = "";
        if (in_array('importMs', $this->rights)){
            $template = file_get_contents("templates/import/base.html");
            $marker = array();
            
            $importMs = file_get_contents("templates/import/tabs/importMs.html");
            $importMs_marker = array();
            
            $marker['###importMs_tab###'] = $this->replaceMarker($importMs,$importMs_marker);
            
            $template = $this->replaceMarker($template, $marker);
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        return $template;
    }
    
    //Anzeige Freigaben von Einsatzplänen
    function getFreigabeR(){
        global $basics;
        if (in_array('rosterFreig', $this->rights) && $basics['ep']){
            return $this->getEPlanung(true);
        }
    }
    
    //Einsatzplan anzeigen
    function getEPlanung($freigabe = false){
        global $basics;
        
        if ($this->loggedIn && $basics['ep']){
            if (isset($_GET['ts'])) $timestamp = $_GET['ts'];
            else $timestamp = time();
            //wenn Wochennavi
            if(array_key_exists('week',$_GET)){
                $timestamp = mktime( 0, 0, 0, 1, 1,  $_GET['year'] ) + ( ($_GET['week']-1) * 7 * 24 * 60 * 60 );            
            }

            if ($freigabe) {
                $template = file_get_contents("templates/eplanung/freigabe.html");
            } else {
                $template = file_get_contents("templates/eplanung/base.html");
            }
            
            $act = file_get_contents("templates/eplanung/tabs/act.html");
            $marker = array();
            $marker['###WEEK###'] = date("W", $timestamp);
            $marker['###YEAR###'] = date("Y", $timestamp);
            if ($freigabe) $marker['###FREIGABE###'] = 1;
            else $marker['###FREIGABE###'] = 0;

            $calendar = new Calendar();
            $days = $calendar->getDaysForWeekView($timestamp);

            $writtendays = "";
            $ist_freigegeben = false;
            $freigegebeneTage = 0;
            foreach ($days as $day){
                $day_template = file_get_contents("templates/eplanung/week_weekday.html");
                $day_marker = array();
                if ($day['weekday'] == 6 || $day['weekday'] == 0 || $day['weekday'] == 7){
                    $day_template = file_get_contents("templates/eplanung/week_weekend.html");
                } 
                $day_marker = $this->getBaseDayMarker($day);
                $day_marker = $this->getDayItems($day, $day_marker, 'week', $freigabe);
                if (($day_marker['###PLAN_ITEMS###'] == 0) || ($day_marker['###PLAN_ITEMS_F###'] != 0 && $day_marker['###PLAN_ITEMS###'] == $day_marker['###PLAN_ITEMS_F###'])) {
                    $freigegebeneTage ++;
                }
                $writtendays .= $this->replaceMarker($day_template, $day_marker);
            }
            if ($freigegebeneTage == count($days)) $ist_freigegeben = true;
            $marker['###DAYS###'] = $writtendays;
            $marker['###FREIGABE_BUTTON###'] = '';
            if ($freigabe){
                if ($ist_freigegeben){
                    $marker['###FREIGABE_BUTTON###'] = '<div class="col-5">wurde bereits freigegeben / es liegt keine Planung vor</div>';   
                } else {
                    $marker['###FREIGABE_BUTTON###'] = '<div class="col-2"><button type="button" class="btn btn-success" onclick="freigabeRoster('.$marker['###WEEK###'].','.$marker['###YEAR###'].')">Woche freigeben</button></div>';    
                }                
            }

            $act =  $this->replaceMarker($act,$marker);
            //$next = file_get_contents("templates/eplanung/tabs/next.html");
            $date = new \DateTime();
            $act_week = $date->format("W");
            //muss aber berechnet werden
            //$next_week = date( 'W', strtotime( 'next week' ) );
            $template = str_replace("###ACTWEEK###",$act_week,$template);
            //$template = str_replace("###NEXTWEEK###",$next_week,$template);
            $template = str_replace("###act_tab###", $act, $template);
            //$template = str_replace("###next_tab###", $next, $template);            
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        return $template;
    }
    
    function getDayItems($day, $day_marker, $item_type, $freigabe = false){
        $oData = new OData();
        $template = file_get_contents("templates/eplanung/".$item_type."_dayitem.html");
        
        //Fest geplante Einträge
        $plaene = $oData->getPlanForDay($day['timestamp'], $freigabe);
        $day_marker['###PLAN_ITEMS###'] = 0;
        $day_marker['###PLAN_ITEMS_F###'] = 0;
        foreach ($plaene as $plan){
            $marker = "";
            $item_marker = array();
            $item_marker['###ITEMTYPE###'] = 'plan';
            $item_marker['###ITEMID###'] = $plan->LID;
            $item_marker['###NUTZER###'] = $plan->Nutzer->STR_NACHNAME.', '.$plan->Nutzer->STR_VORNAME;
            $item_marker['###NUTZERID###'] = $plan->Nutzer->LID;
            $item_marker['###MAIL###'] = '<a href="mailto:'.$plan->Nutzer->STR_EMAIL.'">'.$plan->Nutzer->STR_EMAIL.'</a>';
            $item_marker['###TO_PLAN###'] = '';
            $item_marker['###COUNTING###'] = '';
            if ($plan->Nutzer->LID == $this->user['uid']){
                $item_marker['###COUNTING###'] = 'mine';
            }
            $day_marker['###PLAN_ITEMS###'] ++;
            if ($plan->DT_FREIGABEAM != null) $day_marker['###PLAN_ITEMS_F###'] ++;
            
            $item = $this->replaceMarker($template, $item_marker);

            if ($plan->Schicht){
                switch ($plan->Schicht->STR_TITEL){
                    case "Fruehschicht":
                        $marker = "FS_".strtoupper($plan->STR_TAETIGKEIT);
                        break;
                    case "Spaetschicht":
                        $marker = "SS_".strtoupper($plan->STR_TAETIGKEIT);
                        break;
                    case "Wochenend":
                        $marker = "WS_".strtoupper($plan->STR_TAETIGKEIT);
                        break;
                    default:
                        $marker = "SP";
                }
            } else {
                $marker = "SP";
            }
            $marker .= '_WITEMS';

            if (array_key_exists('###'.$marker.'###',$day_marker)) $day_marker['###'.$marker.'###'] .= $item;
            else $day_marker['###'.$marker.'###'] = $item;
        }
        
        return $day_marker;
    }
    
    function getBaseDayMarker($day){
        $day_marker = array();
        
        if (!$day['act_month']) $day_marker['###ACT_MONTH###'] = "day_other_month";
        $day_marker['###DATESTRING###'] = $day['datestring'];
        $day_marker['###WEEKDAY###'] = $day['weekday'];
        $day_marker['###TIMESTAMP###'] = $day['timestamp'];
        if ($day['weekday'] != 0 && $day['weekday'] < 6){
            foreach ($day['schichten']['Fruehschicht']['Besetzung']['results'] as $bes){
                $day_marker['###FS_'.strtoupper($bes['STR_TYP']).'_BEDARF###'] = $bes['L_ANZAHL'];
            }
            foreach ($day['schichten']['Spaetschicht']['Besetzung']['results'] as $bes){
                $day_marker['###SS_'.strtoupper($bes['STR_TYP']).'_BEDARF###'] = $bes['L_ANZAHL'];
            }
        } else {
            foreach ($day['schichten']['Wochenend']['Besetzung']['results'] as $bes){
                $day_marker['###WS_'.strtoupper($bes['STR_TYP']).'_BEDARF###'] = $bes['L_ANZAHL'];
            }
        }            
        
        return $day_marker;
    }
    
    
    //Liste der Nutzer mit Rechten
    function getUserList(){
        $template = "";
        $oData = new \base\oData;
        
        if (in_array('admUser', $this->rights)){
            $template = file_get_contents("templates/tables/user.html");
            $row_template = file_get_contents("templates/tables/user_row.html");
            $rows = $oData->getUserList($row_template);
            
            $template = str_replace("###ROWS###",$rows,$template);
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        
        return $template;
    }
    
    //Liste der Einträge Ohne Survnet Eintragung
    function getSvNetO(){
        $template = "";
        $oData = new\base\OData;
        if (in_array('survnet',$this->rights)){
            $template = file_get_contents("templates/tables/survnet.html");
            $row_template = file_get_contents("templates/tables/survnet_row.html");
            $rows = $oData->getSvNetO($row_template);
            
            $template = str_replace("###ROWS###",$rows,$template);
        } else {
            $template = file_get_contents("templates/need_login.html");
        }
        
        return $template;
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
    function getAdd($id = false){
        $oData = new\base\OData;
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
        if ($id){
            $add = $oData->getSingle($id, $template, "", "", "", "", "");
            $add = $this->eraseMarkers($add);   
        } else {
            $add = $this->eraseMarkers($template);    
        }        
        
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
            if ($oData->checkSperre($id, $this->user['uid']) || $oData->isLocked($id)){
                $this->rights[] = 'locked';
                $oData->rights = $this->rights;
            }
            
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
                if (/*$this->getMainRight() != 'read' && */in_array('ovEdit', $this->rights) && !in_array('locked',$this->rights)){
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
            case "freigabeQ":
                $script.= "<script src=\"js/qfreigaben.js\"></script>";
                break;
            case "freigabeT":
                $script.= "<script src=\"js/tfreigaben.js\"></script>";
                break;
            case "freigabeR":
                $script.= "<script src=\"js/rfreigaben.js\"></script>";
                break;
            case "ePlanung":
                $script.= "<script src=\"js/eplanung.js\"></script>";
                break;
            case 'import':
                $script.= "<script src=\"js/import.js\"></script>";
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
    
    //Marker ersetzen und leere entfernen
    function replaceMarker($template, $marker){
        foreach($marker as $m => $v){
            $template = str_replace($m, $v, $template);
        }
        // preg_replace('/CROPSTART[\s\S]+?CROPEND/', '', $string);
        $template = preg_replace('/###[\s\S]+?###/','',$template);
        return $template;
    }
}

?>