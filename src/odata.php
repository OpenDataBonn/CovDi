<?php
namespace base;
/* 
Klasse zur Verwaltung der Daten über OData. 
Handhabt den Kontakt zum OData-Server und bereitet den Rücklauf auf.
*/

class OData {
    protected $basics;
    
    private $serviceRoot = '';
    private $serviceRootDienste = '';
    private $serviceRootEp = '';
    private $serviceRootEpDienste = '';
    
    public $rights = '';
    private $user = '';
    
    function __construct() {
        //basics müssen als Global definiert werden
        global $basics;
        $this->basics =& $basics;
        
        $this->serviceRoot = $basics['serviceRoot'];
        $this->serviceRootDienste = $basics['serviceRootDienste'];
        if ($basics['ep']){
            $this->serviceRootEp = $basics['serviceRootEp'];
            $this->serviceRootEpDienste = $basics['serviceRootEpDienste'];
        }
        
        if (isset($_SESSION[$basics['session']]['user'])){
            $this->rights = $_SESSION[$basics['session']]['user']['rechte'];          
            $this->user = $_SESSION[$basics['session']]['user'];          
        } 
        //var_dump($this->user);
    }
    
    /*
    Basisfunktion für die Kommunikation mit OData. Httpful wird für das Basisprotokoll genutzt. 
    Da der Aufruf fast immer identisch ist für den Abruf der Daten, wurde der Aufruf hier gebündelt.
    */
    function fetchWithUri($url){
        //Httpful get
        $response = \Httpful\Request::get($url)
            //gibt json
            ->sendsJson()
            //user und pass für den user in intrexx
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            //braucht json zurück
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->send();
        //OData Protokoll 2 ist immer in d=> geschachtelt
        if (property_exists($response->body,'d')){
            $result = $response->body->d;        
            return $result;    
        } else {
            return false;
        }             
    }
    
    /*
    Abruf der Endenden Quarantänen. Die Anzahl der Tage, die gezeigt werden wird im View festgelegt.
    */
    function getEndingQus($days){
        //aufbau der URL 
        $url = $this->serviceRootDienste."/getEndingQus?days=".$days;
        $result = $this->fetchWithUri($url);
        
        //templates für die Tabellenzeilen
        $single_row = file_get_contents("templates/tables/qus_ending_row.html"); 
        $empty_row = file_get_contents("templates/tables/qus_ending_emptyrow.html"); 
        $rows = "";
        $day = "";
        
        //wenn es ein ergebnis gibt werden die tabellenzeilen gefüllt
        if ($result){
            foreach ($result->results as $rkey => $rvals){                
                $data = $this->processSingle($rvals); 
                //var_dump($data);
                if ($data['###L_MELDUNG###']){
                    $row = $single_row;
                    foreach ($data as $key => $value){
                        $row = str_replace($key,$value,$row);
                    }                
                    //wenn es nicht mehr der gleiche Tag ist, wird für die Übersichtlichkeit eine leere Zeile eingefügt
                    if ($day != $data['###DT_ENDE###'])//strcmp($day,$data['###DT_ENDE###']) == 0)
                    {
                        $rows = $rows.$empty_row;                    
                    }
                    $day = $data['###DT_ENDE###'];
                    $rows = $rows.$row;
                }
            }
            $rows = $rows.$empty_row; 
            return $rows;
        } else {
            return false;
        }
    }
    
    /*
    Abruf der Änderungsverfolgung für einen Fall
    OData Funktion, wenn andere Daten benötigt werden muss zunächst die OData-Schnittstelle angepasst werden
    */
    function getWeblog($fall){
        $url = $this->serviceRootDienste."/getWeblog?fall='".$fall."'";
        $result = $this->fetchWithUri($url);
        
        $single = file_get_contents("templates/blocks/weblog.html"); 
        $row = file_get_contents("templates/blocks/weblog_row.html"); 
        $log = "";
        
        if ($result){
            foreach ($result->results as $rkey => $rvals){
                $data = $this->processSingle($rvals); 
                $nurl = $this->serviceRoot."/Weblog(".$data['###LID###'].')?$expand=Aenderung';
                $sdata = $this->fetchWithUri($nurl);
                $data = $this->processSingle($sdata);
                //var_dump($data);
                $slog = $single;
                if (!array_key_exists('###STR_NUTZER###', $data) || $data['###STR_NUTZER###'] == ""){
                    $uurl = $this->serviceRoot.'/Nutzer('.$data['###L_NUTZERID###'].')';
                    $udata = $this->fetchWithUri($uurl);
                    $data['###STR_NUTZER###'] = $udata->STR_NACHNAME.', '.$udata->STR_VORNAME;
                }
                foreach ($data as $key => $value){
                    if ($key != 'aenderung'){
                        $slog = str_replace($key,$value,$slog);
                    } else {
                        $alogs = "";
                        //hier muss jetzt noch der eintrag was geändert wurde hin
                        //var_dump($value);
                        if (is_array($value)){
                            foreach ($value as $aenderung){
                                $alog = $row;
                                if ((       $aenderung['###STR_DATENFELD###'] != 'L_LASTEDIT' && $aenderung['###STR_DATENFELD###'] != 'L_TATVERBOTDURCH' && $aenderung['###STR_DATENFELD###'] != 'L_ABSTRICHDURCH' && $aenderung['###STR_DATENFELD###'] != 'L_QUARANTAENEADURCH')
                                        &&  !($aenderung['###TXT_ALTERWERT###'] == "null" && $aenderung['###TXT_NEUERWERT###'] == "false")
                                        &&  !($aenderung['###TXT_NEUERWERT###'] == '1970-01-01 01:00:00.0' && $aenderung['###TXT_ALTERWERT###'] == "null")
                                        &&  !($aenderung['###TXT_ALTERWERT###'] == '1970-01-01 01:00:00.0' && $aenderung['###TXT_NEUERWERT###'] == "null")) 
                                {
                                    foreach($aenderung as $ekey => $evalue){                                    
                                        if ($ekey == "###STR_DATENFELD###"){
                                            $evalue = $this->translateFeldnamen($evalue);
                                        }
                                        if ($evalue == "null") $evalue = "";
                                        if ($evalue == "true") $evalue = "ja";
                                        if ($evalue == "false") $evalue = "nein"; 
                                        $alog = str_replace($ekey, $evalue, $alog);
                                    }    
                                    $alogs = $alogs.$alog;    
                                }                                
                            }                            
                            //echo $alogs;
                        }
                        $slog = str_replace('###AENDERUNGEN###',$alogs,$slog);
                    }
                }   
                $slog = str_replace('###AENDERUNGEN###',"",$slog);
                $log = $slog.$log;
            }             
            return $log;
        } else {
            return false;
        }
    }
    
    /*
    Abruf der Referenzierenden Fälle
    OData Funktion, wenn andere Daten benötigt werden, muss zunächst die OData-Schnitstelle angepasst werden
    */
    function getRefs($fall){
        $url = $this->serviceRootDienste."/getRefs?fall='".$fall."'";
        //echo $url;
        $result = $this->fetchWithUri($url);
        
        $refs = "";
        
        if ($result){
            foreach ($result->results as $rkey => $rvals){
                $data = $this->processSingle($rvals);
                $sref = "<li>";
                $sref .= '<a class="" target="_blank" href="?type=single&id='.$data["###LID###"].'" >'.$data['###STR_BNACHNAME###'].', '.$data['###STR_BVORNAME###'].'</a>';
                $sref .= "</li>";
                
                $refs = $refs.$sref;
            }
        }
        if ($refs != ''){
            $single = file_get_contents("templates/blocks/referenzfaelle.html");
            $refs = str_replace('###LINKS###',$refs,$single);
        }
        
        return $refs;
    }
    
    /*
    Liste der Abstriche ohne Rückmeldung
    OData Funktion, wenn andere Daten benötigt werden muss zunächst die OData-Schnittstelle angepasst werden
    */
    function getAOhneRueck(){
        $url = $this->serviceRootDienste."/getAOhneRueck";
        //echo $url;
        $result = $this->fetchWithUri($url);
        
        $single_row = file_get_contents("templates/tables/as_orueck_row.html"); 
        $empty_row = file_get_contents("templates/tables/as_orueck_emptyrow.html"); 
        $rows = "";
        $day = "";
        
        if ($result){
            foreach ($result->results as $rkey => $rvals){
                $data = $this->processSingle($rvals); 
                if ($data['###L_MELDUNG###']){
                    $row = $single_row;
                    foreach ($data as $key => $value){
                        $row = str_replace($key,$value,$row);
                    }                
                    $rows = $rows.$row;
                }
            }
            $rows = $rows.$empty_row; 
            return $rows;
        } else {
            return false;
        }
    }
    
    function isOldData($id){
        $url = $this->serviceRoot.'/Meldungen('.$id.')';
        $result = $this->fetchWithUri($url);
        
        if ($result->STR_TRANSID == null) return true;
        else return false;
            //var_dump($result->STR_TRANSID);
    }
    
    function isLocked($id){
        if ($id > 0){
            $url = $this->serviceRoot.'/Meldungen('.$id.')';
            $result = $this->fetchWithUri($url);
            //var_dump($result->LID);

            if ($result->B_ABGESCHLOSSEN == true) return true;
            else return false;
        }
        return false;
        //var_dump($result->B_ABGESCHLOSSEN);
    }
    
    function checkDouble($data){
        //var_dump($data);
        foreach ($data as $id => $val){            
            $val = str_replace(" ","%20",$val);
            
            $umlaute = array("/ä/","/ö/","/ü/","/Ä/","/Ö/","/Ü/","/-/","/ß/");
            $replace = array("&auml;","&ouml;","&uuml;","&Auml;","&Ouml;","&Uuml;","&minus;","&szlig;");
            $val = preg_replace($umlaute, $replace, $val);
            
            $val = urlencode($val);
            $data[$id] = $val;
        }
        $data['gebDatum'] = str_replace('.','%2E',$data['gebDatum']);
        $url = $this->serviceRootDienste."/checkDouble?nachname='".$data['nachname']."'&vorname='".$data['vorname']."'&gebDatum='".$data['gebDatum']."'";
        //echo $url.'<br />';
        $result = $this->fetchWithUri($url);
        //echo $result->checkDouble;
        
        if ($result) return $result->checkDouble;
        else return false;
    }
    
    function checkPin($pin){
        //$url = $this->serviceRoot.'/Nutzer?$filter=STR_PIN%20eq%20'.$pin;
        $url = $this->serviceRootDienste."/login?pin='".$pin."'";
        $result = $this->fetchWithUri($url);
        
        //var_dump($result);
        if ($result){
            $rights = explode('||',$result->TXT_RECHTE);
            $login = array(
                'uid'       => $result->LID,
                'vorname'   => $result->STR_VORNAME,
                'nachname'  => $result->STR_NACHNAME,
                'rechte'    => $rights
            );            
            return $login;
        } else {
            return false;
        }
    }
    
    function getBaseReport($template){
        $url = $this->serviceRootDienste."/getBaseReport";
        $result = $this->fetchWithUri($url);
        $data = $this->processSingle(json_decode($result->getBaseReport));
        //var_dump($result->getBaseReport);
        //var_dump($data);
        foreach ($data as $key => $value){
            $template = str_replace($key, $value, $template);
        }
        return $template;
    }
    
    function getQFreigaben($template){
        $url = $this->serviceRootDienste."/getQFreigaben";
        $result = $this->fetchWithUri($url);
        
        $rows = "";
        
        if ($result){
            foreach ($result->results as $rkey => $rvals){
                $data = $this->processSingle($rvals); 
                //var_dump($data);
                if ($data['###LID###']){
                    $row = $template;
                    foreach ($data as $key => $value){
                        $row = str_replace($key,$value,$row);
                    }                

                    $rows = $rows.$row;
                }
            }
            return $rows;
        } else {
            return false;
        }
    }
    
    function getTFreigaben($template){
        $url = $this->serviceRootDienste."/getTFreigaben";
        $result = $this->fetchWithUri($url);
        
        $rows = "";
        
        if ($result){
            foreach ($result->results as $rkey => $rvals){
                $data = $this->processSingle($rvals); 
                //var_dump($data);
                if ($data['###LID###']){
                    $row = $template;
                    foreach ($data as $key => $value){
                        $row = str_replace($key,$value,$row);
                    }                

                    $rows = $rows.$row;
                }
            }
            return $rows;
        } else {
            return false;
        }
    }
    
    function getPin($email){
        $url = $this->serviceRootDienste."/getPin?email='".$email."'";
        $result = $this->fetchWithUri($url);
        
        if ($result){
            //var_dump($result);
        } else {
            return false;
        }
    }
    
    function getSingleFrontPage($id, $template){
        $url = $this->serviceRoot.'/Meldungen('.$id.')';
        $result = $this->fetchWithUri($url); 
        
        if ($result){
            $vars = $this->processSingle($result);
            $today =  new \DateTime();
            $vars['###page_title###'] = "Deckblatt für CovDi Fall Nr. ".$id;
            $vars['###ACT_DATUM###'] = $today->format('d.m.Y');
            $vars['###ACT_USER###'] = '';
            $vars['###ACT_USER_ID###'] = '';
            if (isset($_SESSION[$this->basics['session']]['user'])) {
                $vars['###ACT_USER###'] = $_SESSION[$this->basics['session']]['user']['nachname'].', '.$_SESSION[$this->basics['session']]['user']['vorname'];
                $vars['###ACT_USER_ID###'] = $_SESSION[$this->basics['session']]['user']['uid'];
            }
            
            foreach ($vars as $key => $value){
                if (!is_array($value)) $template = str_replace($key, $value, $template);
            }
        }
        
        return $template;
    }
    
    function getSingle($id, $template, $atemplate, $qtemplate, $ttemplate, $bttemplate = "", $ktemplate = ""){
        $url = $this->serviceRoot.'/Meldungen('.$id.')?$expand=Abstriche,Quarantaenen,Tatverbote,Notizen,Bluttests,Kontakte,Kontakte/Kontaktperson,Sperre,Sperre/Nutzer';
        $result = $this->fetchWithUri($url); 
        //var_dump($this->rights);
        
        if ($result){   
            $abs = "";
            $qus = "";
            $tvs = "";
            $ons = "";
            $bts = "";
            $kts = "";
            $vars = $this->processSingle($result);
            $today =  new \DateTime();
            $vars['###ACT_DATUM###'] = $today->format('d.m.Y');
            $vars['###ACT_USER###'] = '';
            $vars['###ACT_USER_ID###'] = '';
            $vars['###QFREIGABE###'] = '';
            
            if (isset($_SESSION[$this->basics['session']]['user'])) {
                $vars['###ACT_USER###'] = $_SESSION[$this->basics['session']]['user']['nachname'].', '.$_SESSION[$this->basics['session']]['user']['vorname'];
                $vars['###ACT_USER_ID###'] = $_SESSION[$this->basics['session']]['user']['uid'];
            }
            //var_dump($vars);
            if ($result->B_BANDERERAUFENTHALT == false || ($result->STR_BASTRASSE == null && $result->STR_BAPLZ == null)){
                $vars['###display_ao###'] = 'style="display:none"';
            }
            if ($result->STR_BEB1NACHNAME == null && $result->STR_BEB1VORNAME == null && $result->STR_BEB2NACHNAME == null && $result->STR_BEB2VORNAME == null){
                $vars['###display_eb###'] = 'style="display:none"';
            }
            if ($vars['###STR_ABSTRICHTYP###'] == '') $vars['###STR_ABSTRICHTYP###'] = "standard";
            if ($vars['###STR_QUARANTAENETYP###'] == '') $vars['###STR_QUARANTAENETYP###'] = "Neu";
            if ($vars["###L_KONTAKTFALL###"] != 'null' && $vars["###L_KONTAKTFALL###"] != '') $vars["###L_KONTAKTFALL_link###"] = '<a class="btn btn-info btn-sm" target="_blank" href="?type=single&id='.$vars["###L_KONTAKTFALL###"].'" role="button" >zum Fall</a>';
            else $vars["###L_KONTAKTFALL_link###"] = '';
            if ($vars['###B_ABGESCHLOSSEN###'] == 'ja') $vars['###LOCKED###'] = '(abgeschlossen)';
            else $vars['###LOCKED###'] = '';
            //weblog
            $vars['###WEBLOG###'] = $this->getWeblog($id);
            //Referenzierte Fälle
            $vars['###REFS###'] = $this->getRefs($id);
            //Freigabe von Qs im Fall
            if (in_array('freig', $this->rights) && $vars['###B_QUARANTAENE###'] == "ja" && $vars['###B_QFREIGABE###'] != 'ja'){
                $vars['###QFREIGABE###'] = '<button type="button" class="btn btn-info btn-sm" onclick="quarantaene_freigeben('.$vars['###ACT_USER_ID###'].',\''. $vars['###ACT_USER###'].'\','.$vars['###LID###'].')">Quarantäne Freigeben</button>';
            }
            
            //var_dump($abs);   
            $deleteFallButton = true;
            $admms = '';
            foreach ($vars as $key => $value){
                if (!is_array($value)) $template = str_replace($key, $value, $template);
                else {
                    //Abstriche
                    switch($key){
                        case 'abstriche':   
                            $i = 0;
                            foreach ($value as $count => $abstrich){
                                $ab = $atemplate;
                                $i = $i+1;
                                $ab = str_replace("###counter###", $i, $ab);
                                $abstrich['###ADMMS###'] = '';            
                                if ($abstrich["###STR_TYP###"] == "standard") $abstrich["###STR_TYP_TEXT###"] = "Normalabstrich";
                                elseif ($abstrich["###STR_TYP###"] == "spezial1") $abstrich["###STR_TYP_TEXT###"] = "Spezialabstrich (Zwei zeitgleich abgenommene tiefe Rachenabstriche mit getrennten Tupfern)";
                                else $abstrich["###STR_TYP_TEXT###"] = $abstrich["###STR_TYP###"];
                                
                                if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                                    $abstrich['###ADMMS###'] = '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteAbstrich('.$abstrich['###LID###'].')">Löschen</button>';
                                }
                                
                                foreach ($abstrich as $akey => $avalue){                                    
                                    $ab = str_replace($akey, $avalue, $ab);
                                }                                                                
                                if ($ab != "") $abs = $abs . $ab;
                                //$i = $i+1;
                                $deleteFallButton = false;
                            }
                            break;
                        case 'quarantaenen':
                            $i = 0;
                            foreach ($value as $count => $quar){
                                $qu = $qtemplate;
                                $i = $i+1;
                                $qu = str_replace("###counter###", $i, $qu);
                                $quar['###ADMMS###'] = ''; 
                                if ($quar["###L_QAUSLFALL###"] != 'null' && $quar["###L_QAUSLFALL###"] != '') $quar["###L_QAUSLFALL_link###"] = '<a class="btn btn-info btn-sm" target="_blank" href="?type=single&id='.$quar["###L_QAUSLFALL###"].'" role="button" >zum Fall</a>';
                                else $quar["###L_QAUSLFALL_link###"] = '';
                                
                                if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                                    $quar['###ADMMS###'] .= '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteQuarantaene('.$quar['###LID###'].')">Löschen</button>';
                                }
                                if (in_array('abortQs', $this->rights) && !in_array('locked',$this->rights)){
                                    $quar['###ADMMS###'] .= '&nbsp;<button class="btn btn-outline-info btn-sm" type="button" onclick="askAbortQuarantaene('.$quar['###LID###'].')">Abbrechen</button>';
                                }
                                
                                foreach ($quar as $akey => $avalue){
                                    $qu = str_replace($akey, $avalue, $qu);
                                }                                                                
                                if ($qu != "") $qus = $qus . $qu;
                                //$i = $i+1;
                                $deleteFallButton = false;
                            }
                            break;
                        case 'tatverbote':
                            $i = 0;
                            //var_dump($value);
                            foreach ($value as $count => $tatv){
                                $tv = $ttemplate;
                                $i = $i+1;
                                $tv = str_replace("###counter###", $i, $tv);
                                
                                $tatv['###ADMMS###'] = ''; 
                                if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                                    $tatv['###ADMMS###'] = '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteTatverbot('.$tatv['###LID###'].')">Löschen</button>';
                                }
                                
                                foreach ($tatv as $akey => $avalue){
                                    $tv = str_replace($akey, $avalue, $tv);
                                }                                                                
                                if ($tv != "") $tvs = $tvs . $tv;
                                //$i = $i+1;
                                $deleteFallButton = false;
                            }
                            break;
                        case 'notizen':
                            $i = 0;
                            foreach ($value as $count => $note){
                                if ($note['###TXT_NOTIZ###'] != ""){
                                    $no = file_get_contents("templates/blocks/note.html");
                                    $i = $i+1;
                                    $no = str_replace("###counter###", $i, $no);
                                    
                                    $note['###ADMMS###'] = ''; 
                                    if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                                        $note['###ADMMS###'] = '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteNote('.$note['###LID###'].')">Löschen</button>';
                                    }
                                    
                                    foreach ($note as $nkey => $nvalue){
                                        $no = str_replace($nkey, $nvalue, $no);                                    
                                    } 
                                    if ($no != "") $ons = $no.$ons;
                                    $deleteFallButton = false;
                                }
                            }
                            break;
                        case 'bluttests':
                            $i = 0;
                            //var_dump($value);
                            foreach ($value as $count => $bte){
                                $bt = $bttemplate;
                                $i = $i+1;
                                $bt = str_replace("###counter###", $i, $bt);
                                
                                $bte['###ADMMS###'] = ''; 
                                if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                                    $bte['###ADMMS###'] = '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteBluttest('.$bte['###LID###'].')">Löschen</button>';
                                }
                                                                
                                foreach ($bte as $akey => $avalue){
                                    $bt = str_replace($akey, $avalue, $bt);
                                }                                                                
                                if ($bt != "") $bts = $bts . $bt;
                                //$i = $i+1;
                                $deleteFallButton = false;
                            }
                            break;
                        case 'kontakte':
                            $i = 0;
                            //var_dump($value);
                            foreach ($value as $count => $kte){
                                $kt = $ktemplate;
                                $i = $i+1;
                                $kt = str_replace("###counter###",$i, $kt);
                                
                                $kte['###ADMMS###'] = '';
                                if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                                    $kte['###ADMMS###'] .= '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteKontakt('.$kte['###LID###'].')">Löschen</button>';
                                }
                                if ($this->isLocked($kte['###L_FALLNR###']) OR ($this->isLocked($kte['###L_FALLNR###'] == '' && $kte['###REF_6755A00B###'] != ''))){
                                    $kte['###ADMMS###'] .= '&nbsp;<button class="btn btn-outline-info btn-sm" type="button" onclick="createNewFall('.$kte['###LID###'].')">neuer Fall</button>';
                                }
                                                                
                                foreach ($kte as $kkey => $kvalue){
                                    $kt = str_replace($kkey, $kvalue, $kt);
                                }
                                if ($kt != "") $kts = $kts .$kt;
                                $deleteFallButton = false;
                            }
                            break;
                    }
                }
            }            
            if (in_array('openMs',$this->rights) && $vars['###B_ABGESCHLOSSEN###'] == 'ja' && !$this->checkSperre($id, $this->user['uid'])){
                $admms .= '<div class="col-2"><button class="btn btn-info" type="button" onclick="askOpenFall('.$vars['###LID###'].')">Fall Öffnen</button></div>';
            }
            if (in_array('closeMs',$this->rights) && $vars['###B_ABGESCHLOSSEN###'] != 'ja' && !$this->checkSperre($id, $this->user['uid'])){
                $admms .= '<div class="col-2"><button class="btn btn-info" type="button" onclick="askCloseFall('.$vars['###LID###'].')">Fall Abschliessen</button></div>';
            }
            if ($deleteFallButton){
                if (in_array('admMs',$this->rights) && !in_array('locked',$this->rights)){
                   $admms .= '<div class="col-1"><button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteFall('.$vars['###LID###'].')">Löschen</button></div>';
                }
            }
            
            //var_dump($abs);
            $template = str_replace('###ADMMS###', $admms, $template);
            $template = str_replace('###ABSTRICHE###',$abs,$template);
            $template = str_replace('###QUARANTAENEN###',$qus,$template);
            $template = str_replace('###TATVERBOTE###',$tvs,$template);
            $template = str_replace('###OLDNOTES###',$ons,$template);
            $template = str_replace('###BLUTTESTS###',$bts,$template);
            $template = str_replace('###KONTAKTE###',$kts,$template);
            
            return $template;
        } else {
            return file_get_contents("templates/single/not_found.html");
        }
    }
    
    function processSaveData($data){
        $values = array();
        //var_dump($data);
        foreach ($data as $key => $val){
            if (!is_array($val)){
                if ($key != "LID"){
                    $check = explode('_',$key);
                    switch ($check[0]){
                        case 'B':
                            if ($val == "on") $val = true;
                            else $val = false;
                            $values[$key] = $val;
                            break;
                        case 'DT':
                            date_default_timezone_set('UTC');
                            if ($val == "0") $val = "";
                            $values[$key] = "/Date(".strtotime($val)."000+0000)/";
                            break;
                        case 'L':
                            if ($val == "" || count($val) == 0) $values[$key] = null;
                            else $values[$key] = $val;
                            break;
                        case 'STR':
                            $values[$key] = htmlentities($val);
                            break;
                        default:
                            $values[$key] = $val;
                            break;
                    }                
                }
            } else {
                switch ($key) {
                    case "abstriche":
                        $this->saveAbstriche($val);
                        break;
                    case "quarantaenen":
                        $this->saveQuarantaenen($val);
                        break;
                    case "tatverbote":
                        $this->saveTatverbote($val);
                        break;
                    case "notizen":
                        $this->saveNotizen($val);
                        break;
                    case "bluttests":
                        $this->saveBluttests($val);
                        break;
                    case "kontakte":
                        $this->saveKontakte($val);
                        break;
                }
            }
        }
        return $values;
    }
    
    function setSurvnet($data){        
        $values = $this->processSaveData($data);
        //var_dump($values);
        $update = \Httpful\Request::put($this->serviceRoot.'/Meldungen('.$data['LID'].')')
            //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();    
        return $update;
    }
    
    function setOvAnordnung($data){
        $values = $this->processSaveData($data);
        //var_dump($values);
        $update = \Httpful\Request::put($this->serviceRoot.'/Quarantaenen('.$data['LID'].')')
            //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();  
        return $update;
    }
    
    function saveBluttests($tests){
        //var_dump($tests);
        if (isset($tests['new'])){
            $new = $tests['new'];
            $values = $this->processSaveData($new);
            if($tests['new']['DT_RUECKAM'] != ""){
                //var_dump($values);
                $insert = \Httpful\Request::post($this->serviceRoot.'/Bluttests')
                    //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();                   
            }
            unset($tests['new']);
        }
        foreach ($tests as $id => $data){
            $values = $this->processSaveData($data);
            //var_dump($values);
            $update = \Httpful\Request::put($this->serviceRoot.'/Bluttests('.$data['LID'].')')
                //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                ->body(json_encode($values))
                ->authenticateWith('dmzbonnde', 'seriu3094')
                ->sendsJson()
                ->send();    
        }
    }
    
    function saveKontakte($kons){
        foreach ($kons as $id => $data){
            $values = $this->processSaveData($data);
            //var_dump($values);
            $update = \Httpful\Request::put($this->serviceRoot.'/Kontakte('.$data['LID'].')')
                ->body(json_encode($values))
                ->authenticateWith('dmzbonnde', 'seriu3094')
                ->sendsJson()
                ->send();    
        }
    }
    
    function saveNotizen($notizen){   
        //var_dump($notizen);
        if (isset($notizen['new'])){
            if ($notizen['new']['TXT_NOTIZ'] != ""){
                $values = $this->processSaveData($notizen['new']);
                //var_dump($values);
                $insert = \Httpful\Request::post($this->serviceRoot.'/Notizen')
                    //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();    
                //var_dump($insert);
                //exit;
            }
        }
    }
    
    function saveAbstriche($abstriche){  
        //var_dump($abstriche);
        if (isset($abstriche['new'])){
            $new = $abstriche['new'];
            $values = $this->processSaveData($new);
            if($values['STR_TYP'] != ""){
                //var_dump($values);
                $insert = \Httpful\Request::post($this->serviceRoot.'/Abstriche')
                    //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();   
            }
            unset($abstriche['new']);
        }
        foreach ($abstriche as $id => $data){
            $values = $this->processSaveData($data);
            if (in_array('doc',$this->rights)){
                if (!isset($values['B_DIAGNOSTIKZENTRUM'])) $values['B_DIAGNOSTIKZENTRUM'] = false;                  
                if (!isset($values['B_ZUGESCHICKT'])) $values['B_ZUGESCHICKT'] = false; 
                if (!isset($values['B_BINFORMIERT'])) $values['B_BINFORMIERT'] = false; 
                //B_ZUGESCHICKT, B_BINFORMIERT
            }            
            //var_dump($values);
            $update = \Httpful\Request::put($this->serviceRoot.'/Abstriche('.$data['LID'].')')
                //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                ->body(json_encode($values))
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send();    
        }
    }
    
    function saveQuarantaenen($qs){      
        if (isset($qs['new'])){
            /*$new = $qs['new'];
            $values = $this->processSaveData($new);
            if($values['DT_ANGEORDNETBIS'] != "" && $values['STR_TYP'] != ""){
                $insert = \Httpful\Request::post($this->serviceRoot.'/Quarantaenen')
                    //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();   
            }*/
            unset($qs['new']);
        }
        foreach ($qs as $id => $data){
            $values = $this->processSaveData($data);
            //var_dump($values);
            if (in_array('ovEdit', $this->rights)){//in_array('doc',$this->rights) || in_array('edit',$this->rights)){
                if (!isset($values['B_OVERLASSEN'])) $values['B_OVERLASSEN'] = false;
            }
            $update = \Httpful\Request::put($this->serviceRoot.'/Quarantaenen('.$data['LID'].')')
                //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                ->body(json_encode($values))
                ->authenticateWith('dmzbonnde', 'seriu3094')
                ->sendsJson()
                ->send();    
        }
    }
    
    function saveTatverbote($ts){        
        foreach ($ts as $id => $data){
            $values = $this->processSaveData($data);
            //var_dump($values);
            $update = \Httpful\Request::put($this->serviceRoot.'/Tatverbote('.$data['LID'].')')
                //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                ->body(json_encode($values))
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send();    
        }
    }
    
    function saveQFreigaben($freigaben){
        //var_dump($freigaben);
        $user_id = $freigaben['user_id'];
        $user_string = $freigaben['user_string'];
        $all_clear = true;
        if (is_array($freigaben['qfreigaben'])){
            foreach ($freigaben['qfreigaben'] as $key => $data){
                //var_dump($key);
                if (is_array($data)){
                    //var_dump($data);
                    if (array_key_exists('freigabe', $data) && $data['freigabe'] == 'on'){
                        $values = array();
                        $values['B_QFREIGABE'] = true;
                        $values['L_QFREIGABEDURCH'] = $user_id;
                        $values['STR_QFREIGABEDURCH'] = $user_string;
                        $update = \Httpful\Request::put($this->serviceRoot.'/Meldungen('.$data['lid'].')')
                            ->body(json_encode($values))
                            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                            ->sendsJson()
                            ->send();   
                        $all_clear = $update;
                    }
                }            
            }
        }
        return $all_clear;
    }
    
    function saveTFreigaben($freigaben){
        //var_dump($freigaben);
        $user_id = $freigaben['user_id'];
        $user_string = $freigaben['user_string'];
        $all_clear = true;
        if (is_array($freigaben['tfreigaben'])){
            foreach ($freigaben['tfreigaben'] as $key => $data){
                //var_dump($key);
                if (is_array($data)){
                    //var_dump($data);
                    if (array_key_exists('freigabe', $data) && $data['freigabe'] == 'on'){
                        $values = array();
                        $values['B_TFREIGABE'] = true;
                        $values['L_TFREIGABEDURCH'] = $user_id;
                        $values['STR_TFREIGABEDURCH'] = $user_string;
                        $update = \Httpful\Request::put($this->serviceRoot.'/Meldungen('.$data['lid'].')')
                            ->body(json_encode($values))
                            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                            ->sendsJson()
                            ->send();   
                        $all_clear = $update;
                    }
                }            
            }
        }
        return $all_clear;
    }
    
    function addSingle($data){
        $values = array();
        $values = $this->processSaveData($data);        
        $values = $this->setSingleBooleans($values);
        $values['B_ABGESCHLOSSEN'] = false;
        
        $update = \Httpful\Request::post($this->serviceRoot.'/Meldungen')
            //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();   
        return $update;
    }
    
    function addSingleFromKontakt($data){
        $url = $this->serviceRoot.'/Kontakte('.$data['kontakt'].')?$expand=Kontaktperson';
        $kontakt = $this->fetchWithUri($url);        
        $alterFall = false;
        $kontaktperson = false;
        $neuerFall = array();
        $neuerFall['B_ABGESCHLOSSEN'] = false;
        $fields = array('STR_BNACHNAME','STR_BVORNAME','DT_BGEBURTSDATUM','STR_BSTRASSE','STR_BHAUSNUMMER','STR_BPLZ','STR_BORT','STR_BTELEFON','STR_BEMAIL');
        if ($kontakt->L_FALLNR){
            $alterFall = $this->fetchwithUri($this->serviceRoot.'/Meldungen('.$kontakt->L_FALLNR.')');
            $alterFall = json_decode(json_encode($alterFall),true);
            foreach ($fields as $field){
                $neuerFall[$field] = $alterFall[$field];
            }
        } elseif ($kontakt->Kontaktperson){
            $kontaktperson = json_decode(json_encode($kontakt->Kontaktperson), true);
            foreach ($fields as $field){
                $neuerFall[$field] = $kontaktperson[$field];
            }
            //var_dump($kontakt->Kontaktperson);
        }
        //var_dump($neuerFall);
        if ($neuerFall || $kontaktperson){
            $update = \Httpful\Request::post($this->serviceRoot.'/Meldungen')
                ->body(json_encode($neuerFall))
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send();   
            return $update;
        }
        return false;
    }
    
    function saveSingle($data){
        $this->setSperre($data['LID']);
        $values = array();
        $values = $this->processSaveData($data);        
        $values = $this->setSingleBooleans($values);
        
        $checkVals = $values;
        $checkVals['LID'] = $data['LID'];
        $url = $this->serviceRootDienste."/saveChanges?data='".urlencode(json_encode($checkVals))."'";
        $checked = $this->fetchWithUri($url);
        
        //wenn der check erfolgt ist dann den Datensatz speichern
        $update = \Httpful\Request::put($this->serviceRoot.'/Meldungen('.$data['LID'].')')
            //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();  
        return $update;
    }
    
    function setSingleBooleans($values){
        //base-tab
        $keys = array('B_BADRESSEKONTROLLIERT','B_KONTAKTERKRANKT','B_BEBANDEREMELDEADRESSE','B_KONTAKTERKRANKT','B_CRITINFRA',
                      'B_SYMVORHANDEN','B_RISIKOGRUPPE','B_VIRUSL','B_SYMHUSTEN','B_SYMSCHNUPFEN',
                      'B_SYMHALSSCHMERZEN','B_SYMABGESCHLAGEN','B_SYMFIEBER','B_SYMDURCHFALL','B_SYMLUFTNOT',
                      'B_SYMGERUCH','B_SYMKOPFSCHMERZEN','B_SYMGLIEDERSCHMERZEN','B_KOMPLIKATIONEN','B_GRUNDERKRANKUNG',
                      'B_SCHWANGERSCHAFT','B_STATIONAER','B_STATIONAER','B_BERUF','B_LEGITIMATIONSBESCHEINIGUNG',
                      'B_SYMPNEUMONIE','B_SYMARDS','B_SYMBEATMERKRANKUNG','B_SYMDYSPNOE','B_SYMGERUCHD','B_SYMGESCHMACKD',
                      'B_SYMTACHYKARDIE','B_SYMTACHYPNOE','B_SYMALLGEMEIN','B_RISHERZKREISLAUF','B_RISDIABETES','B_RISLEBER',
                      'B_RISNEURO','B_RISIMMUN','B_RISNIERE','B_RISLUNGE','B_RISKREBS','B_RISSCHWANGER','B_RISPOSTPARTUM');
        foreach($keys as $key){
            if (!isset($values[$key])) $values[$key] = false;  
        }
        
        //doc-tab nur füllen wenn auch doc eingeloggt, sonst werden die werte überschrieben
        if (in_array('doc',$this->rights) || in_array('freig',$this->rights)){
            $dkeys = array('B_HYGIENEAUFKL','B_VERLAUFGESUND','B_VERLAUFVERSTORBEN','B_QUARANTAENE','B_ABSTRICH',
                           'B_ABSTRICHDRINGEND','B_ABSTRICHMOBIL');
            foreach($dkeys as $key){
                if (!isset($values[$key])) $values[$key] = false;  
            }
        }
        return $values;
    }
    
    function getMeldungenSearchString($search, $type, $page = 0, $pageSize = 0){
        //startet mit ?$
        $searchString = "?";
        
        if ($pageSize > 0){
            $searchString .= '$inlinecount=allpages&$top='.$pageSize;
        }
        
        $filter = '$filter=';
        if ($search["search"]) {
            $gbfilter = '';
            $timestamp = strtotime($search["search"]);
            if ($timestamp != false || $timestamp != '') {
                $date = new \DateTime($search["search"]);
                $datestring = $date->format('Y-m-d H:i:s');
                $datestring = str_replace(' ','T',$datestring);
                //echo $datestring.'<br >';
                $gbfilter .= '%20or%20DT_BGEBURTSDATUM%20eq%20datetime%27'.$datestring.'%27';
            }
            //echo $gbfilter.'<br />t: '.$timestamp.'<br />';
            $umlaute = array("/ä/","/ö/","/ü/","/Ä/","/Ö/","/Ü/","/-/","/ß/");
            $replace = array("&auml;","&ouml;","&uuml;","&Auml;","&Ouml;","&Uuml;","&minus;","&szlig;");
            $s = $search["search"];
            $s2 = preg_replace($umlaute, $replace, $s);  
            $s2 = urlencode($s2);
            $s2 = str_replace(' ','%20',$s2);
            $s2 = str_replace('-','%2D',$s2);
            $search["search"] = urlencode($search["search"]);
            $search["search"] = str_replace(' ','%20',$search["search"]);
            $search["search"] = str_replace('-','%2D',$search["search"]);
            //$search["search"] = str_replace('-','%2D',$search["search"]);
            //DT_BGEBURTSDATUM, STR_BNACHNAME, STR_BVORNAME
            //2012-05-29T09:13:28
            $filter .= '(';
            //Suche nach Vorname
            $filter .= 'STR_BVORNAME%20eq%20%27'.$search["search"].'%27';
            $filter .= '%20or%20STR_BVORNAME%20eq%20%27'.$s2.'%27';
            //Nachname
            $filter .= '%20or%20(substringof(%27'.$search["search"].'%27,STR_BNACHNAME))';
            $filter .= '%20or%20(substringof(%27'.$s2.'%27,STR_BNACHNAME))';
            //Strasse
            $filter .= '%20or%20(substringof(%27'.$search["search"].'%27,STR_BSTRASSE))';
            $filter .= '%20or%20(substringof(%27'.$s2.'%27,STR_BSTRASSE))';
            //gemeinsame Ausbruchskennung
            $filter .= '%20or%20(substringof(%27'.$search["search"].'%27,STR_INDEXFALL))';
            $filter .= '%20or%20(substringof(%27'.$s2.'%27,STR_INDEXFALL))';
            //Geburtsdatum
            $filter .= $gbfilter;
            $filter .= ')';
            //echo $filter;
        }
        if (!$search['closed']){
            if ($filter != '$filter=') $filter .= '%20and%20';
            $filter .= '(B_ABGESCHLOSSEN%20eq%200)';
        }
        
        switch ($type){
            case "all":
                break;
            case "new":
                if ($filter != '$filter=') $filter .= '%20and%20';;
                $filter .= '(';
                $filter .= '(L_LASTEDIT%20eq%20null)%20and%20(STR_TRANSID%20ne%20null)'; 
                //$filter .= '%20or%20(DTEDIT%20eq%20DTINSERT%20and%20STR_TRANSID%20eq%20null)';
                $filter .= ')';
                break;
        }
        
        if ($filter != '$filter=') {
            if ($searchString != "?") $searchString .= "&";
            $searchString .= $filter;
        }
        if ($search['sorting'] != "LID"){
            if ($searchString != "?") $searchString .= "&";
            $searchString .= '$orderby='.$search['sorting'];
        }
        
        if ($page > 1 && $pageSize > 0){
            $skipper = $pageSize * ($page -1);
            $searchString .= '&$skip='.$skipper;
        }
        
        //echo $searchString;
        if ($searchString == "?") return "";
        else return $searchString;
    }
    
    function getMeldungenCount($search,$type="all"){
        $url = $this->serviceRoot.'/Meldungen/$count';        
        $url .= $this->getMeldungenSearchString($search, $type);
        
        $response = \Httpful\Request::get($url)
            ->sendsJson()
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->send();
        return $response;
    }
    
    function getMeldungen($search, $type, $page = 0, $pageSize = 0){
        $url = $this->serviceRoot.'/Meldungen'.$this->getMeldungenSearchString($search, $type, $page, $pageSize).'&$expand=Sperre';
        $result = $this->fetchWithUri($url);  
        
        return $result;
    }  
        
    function getMeldungenTableRows($page = 1, $pageSize = 15, $search, $type = "all"){
        $meldungen = $this->getMeldungen($search, $type, $page, $pageSize);
        //var_dump($meldungen);
        $single_row = file_get_contents("templates/tables/table_row.html"); 
        $rows = "";
        
        foreach ($meldungen->results as $result){
            //echo 'DTEDIT '.$result->DTEDIT.'<br/>';
            $beratung = $this->formatDate($result->DTINSERT);
            if ($beratung == '') $beratung = $this->formatDate($result->DT_BERATUNGDATUM);
            $short_notiz = '';
            if (strlen($result->TXT_NOTIZEN) > 0) $short_notiz = substr($result->TXT_NOTIZEN, 0, 15).'...';
            $vars = array(
                '###lid###' => $result->LID,
                '###beratung_datum###'  => $beratung,
                '###b_nachname###'      => $result->STR_BNACHNAME,
                '###b_vorname###'       => $result->STR_BVORNAME,
                '###b_geburtsdatum###'  => $this->formatDate($result->DT_BGEBURTSDATUM),
                '###b_strasse###'       => $result->STR_BSTRASSE,
                '###b_hausnummer###'    => $result->STR_BHAUSNUMMER,
                '###b_plz###'           => $result->STR_BPLZ,
                '###b_ort###'           => $result->STR_BORT,
                '###b_telefon###'       => $result->STR_BTELEFON,
                '###b_email###'         => $result->STR_BEMAIL,
                '###notiz###'           => $result->TXT_NOTIZEN,
                '###short_notiz###'     => $short_notiz,
                '###dtedit###'          => $this->formatDate($result->DTEDIT),
                '###crit_infra###'      => $result->STR_CRITINFRATYP,
                '###str_falltyp###'     => $result->STR_STATUSFALLTYP,
            );
            if (count($result->Sperre->results) > 0) $vars['###gesperrt###'] = "gesperrt";
            else $vars['###gesperrt###'] = "";
            
            $row = $single_row;
            //echo $result->DTEDIT."<br />";
            foreach ($vars as $tag => $value){
                $row = str_replace($tag, $value, $row);                
            }
            $rows = $rows . $row;              
        }  
        return $rows;
    }
    
    function formatDate($value, $format = 'd.m.Y',$type = "local") {
        $utc = new \DateTimeZone("UTC");
        $local = new \DateTimeZone("Europe/Berlin");
        
        $value = str_replace("/Date(","",$value);
        $value = str_replace("000+0000)/","",$value);
        /*$value = str_replace("00+0000)/","",$value);
        $value = str_replace("0+0000)/","",$value);*/
        $value = str_replace("+0000)/","",$value);        
        $value = str_replace(")/","",$value);
        //$value = $value / 1000;
        //$value = (float)$value;
        $date = "";
        //echo $value .'<br />';
        if ($value > 0 && strlen($value) > 10){
            $value = substr($value,0,10);
        }
        if ($value != 0) {
            try {
                $dt = new \DateTime("@$value", $local);
                if ($type == 'utc') $dt->setTimeZone($utc);
                else $dt->setTimeZone($local);
                //$dt->add(new \DateInterval('PT1H'));
                $date = $dt->format($format);
            } catch (Exception $e) {
                echo "Datumsfehler: ".$e->getMessage()."\n";
            }
        }
        return $date;
    }
    
    function formatBoolean($value){
        if ($value == null) return "";
        if ($value){
            return "ja";
        } else {
            return "nein";
        }
    }
    
    function processSingle($single, $dtinserttype = 'utc'){
        $vars = json_decode(json_encode($single), true);
        $processed = array();
        //$processed['abstriche'] = array();
        
        foreach ($vars as $key => $value){
            if (!is_array($value)){ 
                $check = explode('_',$key);
                $key = strtoupper($key);
                switch ($check[0]){
                    case 'B':
                        $processed['###'.$key.'###'] = $this->formatBoolean($value);
                        if ($value) $processed['###'.$key.'_checked###'] = 'checked="checked"';
                            else $processed['###'.$key.'_checked###'] = '';
                        break;
                    case 'DT':                                          
                        $processed['###'.$key.'###'] = $this->formatDate($value);
                        break;
                    case 'DTINSERT':  
                        $processed['###'.$key.'###'] = $this->formatDate($value,'d.m.Y H:i', $dtinserttype);
                        break;
                    case 'L':
                        if ($value == 0 || $value == "") $processed['###'.$key.'###'] = '';
                            else $processed['###'.$key.'###'] = $value;
                    default:                        
                        $processed['###'.$key.'###'] = $value;
                        break;
                }
            } else {    
                switch($key){
                    case "Abstriche":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){                        
                            foreach ($value['results'] as $counter => $abstrich){
                                $abstrich = $this->processSingle($abstrich);
                                //var_dump($abstrich);
                                $processed['abstriche'][] =  $abstrich;
                            }
                        }
                        break;
                    case "Quarantaenen":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){                        
                            foreach ($value['results'] as $counter => $quar){
                                $quar = $this->processSingle($quar);
                                //var_dump($abstrich);
                                if ($quar['###STR_OVERLASSEN###'] != '') $quar['###B_OVERLASSEN###'] = 'ja';
                                $processed['quarantaenen'][] =  $quar;
                            }
                        }
                        break; 
                    case "Tatverbote":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){                        
                            foreach ($value['results'] as $counter => $quar){
                                $quar = $this->processSingle($quar);
                                //var_dump($abstrich);
                                $processed['tatverbote'][] =  $quar;
                            }
                        }
                        break; 
                    case "Notizen":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){
                            foreach ($value['results'] as $counter => $note){
                                $note = $this->processSingle($note, '');
                                $processed['notizen'][] = $note;
                            }
                        }
                        break;
                    case "Bluttests":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){                        
                            foreach ($value['results'] as $counter => $quar){
                                $quar = $this->processSingle($quar);
                                //var_dump($abstrich);
                                $processed['bluttests'][] =  $quar;
                            }
                        }
                        break;
                    case "Kontakte":
                        //var_dump($value);
                        if (array_key_exists('results', $value) && count($value['results']) > 0){                        
                            foreach ($value['results'] as $counter => $quar){
                                //var_dump($quar['Kontaktperson']);
                                if ($quar['L_FALLNR']){
                                    $url = $this->serviceRoot.'/Meldungen('.$quar['L_FALLNR'].')';
                                    $zusatz = $this->processSingle($this->fetchWithUri($url));
                                } else if ($quar['Kontaktperson']) $zusatz = $this->processSingle($quar['Kontaktperson']);
                                unset($zusatz['###LID###']);
                                
                                $quar = $this->processSingle($quar);      
                                $quar['###FALL_LINK###'] = '';
                                if ($quar['###L_FALLNR###']) $quar['###FALL_LINK###'] = '<a class="btn btn-info btn-sm" target="_blank" href="?type=single&id='.$quar["###L_FALLNR###"].'" >Zum Fall</a>';
                                // TODO: Link für die generierung von neuem Fall ... else $quar['###FALL_LINK###'] = '';
                                $processed['kontakte'][] =  array_merge($quar,$zusatz);
                            }
                        }
                        break;
                    case "Sperre":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){
                            $sperre = $value['results'][0];
                            //var_dump($sperre['Nutzer']);
                            if ($sperre['Nutzer']['LID'] == $this->user['uid']) $processed['###SPERRE_INFO###'] = '';
                            else {
                                $processed['###SPERRE_INFO###'] = file_get_contents('templates/blocks/sperre_info.html');
                                $processed['###SPERRE_INFO###'] = str_replace('###STR_NACHNAME###',$sperre['Nutzer']['STR_NACHNAME'],$processed['###SPERRE_INFO###']);
                                $processed['###SPERRE_INFO###'] = str_replace('###STR_VORNAME###',$sperre['Nutzer']['STR_VORNAME'],$processed['###SPERRE_INFO###']);
                            }
                        }
                        break;
                    case "Aenderung":
                        if (array_key_exists('results', $value) && count($value['results']) > 0){                        
                            foreach ($value['results'] as $counter => $quar){
                                $quar = $this->processSingle($quar);
                                //var_dump($abstrich);
                                $processed['aenderung'][] =  $quar;
                            }
                        }
                        break;
                }                
            }
        }
        //Workaround für Kontakterkrankt
        if (array_key_exists('###STR_KONTAKTERKRANKT###',$processed) && $processed['###STR_KONTAKTERKRANKT###'] == ""){
            $processed['###STR_KONTAKTERKRANKT###'] = $processed['###B_KONTAKTERKRANKT###'];
        }
        //Workaround für Notizen zur Quarantäne 
        if ((array_key_exists('###TXT_NOTIZEN###', $processed) && array_key_exists('###STR_INFO###', $processed)) && $processed['###TXT_NOTIZEN###'] == ""){
            $processed['###TXT_NOTIZEN###'] = $processed['###STR_INFO###'];
        }
        //Workaround für Kategorie des Kontakts
        if (array_key_exists('###STR_KONTAKTKATEGORIE###',$processed)){
            switch ($processed['###STR_KONTAKTKATEGORIE###']){
                case "KP1": 
                    $processed['###STR_KONTAKTKATEGORIE###'] = "KP I";
                    break;
                case "KP2":
                    $processed['###STR_KONTAKTKATEGORIE###'] = "KP II";
                    break;
                case "KP3":
                    $processed['###STR_KONTAKTKATEGORIE###'] = "KP III";
                    break;
            }            
        }
        //Workaround für Info Txt Quarantäne
        if (array_key_exists('###B_QUARANTAENE###',$processed)){
            if ($processed['###B_QUARANTAENE###'] != 'ja') $processed['###TXT_QUARANTAENEINFO###'] = '';
        }
        
        //var_dump($processed);
        return $processed;
    }
    
    function delete($type, $id){
        $delete = false;        
        if (in_array('admMs',$this->rights)){
            switch($type){
                case 'abstrich':                
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Abstriche('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send();   
                    break;      
                case 'quarantaene':
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Quarantaenen('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send();   
                    break;
                case 'bluttest':
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Bluttests('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send(); 
                    break;
                case 'tatverbot':
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Tatverbote('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send(); 
                    break;
                case 'note':
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Notizen('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send(); 
                    break;
                case 'kontakt':
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Kontakte('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send(); 
                    break;
                case 'fall':
                    $this->removeSperre();
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Meldungen('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send(); 
                    break;
            }
        }
        
        return $delete;
    }
    
    function openCloseMeldung($type, $id){        
        $values = false;
        switch ($type){
            case 'open':
                if (in_array('openMs',$this->rights)){
                    $values = array();
                    $values['LID'] = $id;
                    $values['B_ABGESCHLOSSEN'] = false;
                }
                break;
            case 'close':
                if (in_array('closeMs',$this->rights)){
                    $values = array();
                    $values['LID'] = $id;
                    $values['B_ABGESCHLOSSEN'] = true;
                }
                break;
        }
        //var_dump($type);
        if(is_array($values)){
            $update = \Httpful\Request::put($this->serviceRoot.'/Meldungen('.$id.')')
                        ->body(json_encode($values))
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send();   
            return $update;
        }
    }
    
    function abortQuarantaene($id, $date, $durch){
        $values = false;
        if (in_array('abortQs', $this->rights)){
            $values = array();
            $values['LID'] = $id;
            $values['DT_ABGEBROCHENAM'] = $date;
            $values['STR_ABGEBROCHENDURCH'] = $durch;
            $values = $this->processSaveData($values);
            //var_dump($values);
            
            $update = \Httpful\Request::put($this->serviceRoot.'/Quarantaenen('.$id.')')
                        ->body(json_encode($values))
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send();   
            
            if (!$update->hasErrors()){
                $url = $this->serviceRootDienste."/sendMail?type='abortQ'&id='".$id."'";
                $result = $this->fetchWithUri($url);
            }
            
            return $update;
        }
    }
    
    function searchKontaktperson($term){
        $searchString = "?";
        $filter = '$filter=';
        if ($term != '') {
            $umlaute = array("/ä/","/ö/","/ü/","/Ä/","/Ö/","/Ü/","/-/","/ß/");
            $replace = array("&auml;","&ouml;","&uuml;","&Auml;","&Ouml;","&Uuml;","&minus;","&szlig;");
            $s = $term;
            $s2 = preg_replace($umlaute, $replace, $s);  
            $s2 = urlencode($s2);
            $s2 = str_replace(' ','%20',$s2);
            $s2 = str_replace('-','%2D',$s2);
            $term = urlencode($term);
            $term = str_replace(' ','%20',$term);
            $term = str_replace('-','%2D',$term);
            $filter .= '(';
            $filter .= '(substringof(%27'.$term.'%27,STR_BNACHNAME))';
            $filter .= '%20or%20(substringof(%27'.$s2.'%27,STR_BNACHNAME))';
            $filter .= ')';            
            //echo $filter;
        }
        
        if ($filter != '$filter=') {
            if ($searchString != "?") $searchString .= "&";
            $searchString .= $filter;
        }
        if ($searchString != "?") $searchString .= "&";
        $searchString .= '$orderby=STR_BNACHNAME';
        
        $url = $this->serviceRoot.'/Kontaktpersonen'.$searchString;
        $response = $this->fetchWithUri($url);  
        $result = array();
        foreach ($response->results as $person){
            $item = array();
            $item['lid'] = $person->LID;
            $item['value'] = $person->STR_BNACHNAME;
            $item['label'] = $person->STR_BNACHNAME.', '.$person->STR_BVORNAME.' ('.$this->formatDate($person->DT_BGEBURTSDATUM).')';
            $item['vorname'] = $person->STR_BVORNAME;
            $item['strasse'] = $person->STR_BSTRASSE;
            $item['hausnummer'] = $person->STR_BHAUSNUMMER;
            $item['plz'] = $person->STR_BPLZ;
            $item['ort'] = $person->STR_BORT;
            $item['telefon'] = $person->STR_BTELEFON;
            $item['email'] = $person->STR_BEMAIL;
            $item['geburtsdatum'] = $this->formatDate($person->DT_BGEBURTSDATUM);
            $item['mid'] = $person->REF_B860BC9E;
            $result[] = $item;
        }
        $murl = $this->serviceRoot.'/Meldungen'.$searchString;
        $mresponse = $this->fetchWithUri($murl);
        //var_dump($mresponse);
        foreach ($mresponse->results as $meldung){
            $item = array();
            $item['lid'] = null;
            $item['value'] = $meldung->STR_BNACHNAME;
            $item['label'] = $meldung->STR_BNACHNAME.', '.$meldung->STR_BVORNAME.' ('.$this->formatDate($meldung->DT_BGEBURTSDATUM).') Fall: '.$meldung->LID;
            $item['vorname'] = $meldung->STR_BVORNAME;
            $item['strasse'] = $meldung->STR_BSTRASSE;
            $item['hausnummer'] = $meldung->STR_BHAUSNUMMER;
            $item['plz'] = $meldung->STR_BPLZ;
            $item['ort'] = $meldung->STR_BORT;
            $item['telefon'] = $meldung->STR_BTELEFON;
            $item['email'] = $meldung->STR_BEMAIL;
            $item['geburtsdatum'] = $this->formatDate($meldung->DT_BGEBURTSDATUM);
            $item['mid'] = $meldung->LID;
            $result[] = $item;
        }
        
        return $result;
    }
    
    function addKontaktperson($data){
        //var_dump($data);
        $values = array();
        $type = $data['type'];
        unset($data['type']);
        $fallNr = $data['fallNr'];
        unset($data['fallNr']);
        $values = $this->processSaveData($data); 
        if ($data['LID'] > 0 && $type == '') {
            $update = \Httpful\Request::put($this->serviceRoot.'/Kontaktpersonen('.$data['LID'].')')
                ->body(json_encode($values))
                ->authenticateWith('dmzbonnde', 'seriu3094')
                ->sendsJson()
                ->send();  
            return array('kp',$data['LID']);
        } elseif ($fallNr > 0) {
            return array('f', $fallNr);
        } else {
            unset($data['LID']);            
            $update = \Httpful\Request::post($this->serviceRoot.'/Kontaktpersonen')
                ->body(json_encode($values))
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send();   
            return $update;
        }
    }
    
    function connectKontaktperson($data){
        $values = array();
        if ($data['kontaktperson']) $values['REF_6755A00B'] = $data['kontaktperson'];
        $values['FKLID'] = $data['meldung'];
        if ($data['fallnr']) $values['L_FALLNR'] = $data['fallnr'];
        //var_dump($values);
        $update = \Httpful\Request::post($this->serviceRoot.'/Kontakte')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();    
        return $update;
    }
    
    function addNewQ($data){
        $values = array();
        $values = $this->processSaveData($data); 
        if ($values['STR_VERANLASSTDURCH'] == '') $values['STR_VERANLASSTDURCH'] = $this->user['nachname'].', '.$this->user['vorname'];
        //var_dump($data);
        
        $update = \Httpful\Request::post($this->serviceRoot.'/Quarantaenen')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();   
        return $update;
    }
    
    function setSperre($meldung){        
        $this->removeSperre();
        $values = array('FKLID'=>$this->user['uid'],'REF_9BDBF206'=>$meldung);
        //var_dump($values);
        $update = \Httpful\Request::post($this->serviceRoot.'/Sperre')
            ->body(json_encode($values))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();    
        return $update;
    }
    
    function checkSperre($meldung){        
        $filter = '?$expand=Sperre/Meldung&$filter=(REF_9BDBF206%20eq%20'.$meldung.')';   
        //echo($filter);
        $url = $this->serviceRoot.'/Sperre'.$filter;
        $response = $this->fetchWithUri($url);
        
        foreach($response->results as $sperre){
            if ($sperre->FKLID != $this->user['uid']) {
                return true;            
            }
        }
        
        $this->setSperre($meldung);
        return false;
    }
    
    function removeSperre(){
        $filter = '?$filter=(FKLID%20eq%20'.$this->user['uid'].')';   
        $url = $this->serviceRoot.'/Sperre'.$filter;
        $response = $this->fetchWithUri($url);
        
        foreach($response->results as $sperre){
            $delete = \Httpful\Request::delete($this->serviceRoot.'/Sperre('.$sperre->LID.')')
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send();   
        }
    }
    
    function getSvNetO($template){
        $filter = '?$filter=(B_SURVNETEINGABE%20eq%20null)&$orderby=DTINSERT%20desc&$top=200';
        //echo $filter;
        $url = $this->serviceRoot.'/Meldungen'.$filter;
        $rows = '';
        
        $response = $this->fetchWithUri($url);
        foreach ($response->results as $meldung){
            $row = $template;
            $row = str_replace('###LID###',$meldung->LID,$row);
            $row = str_replace('###STR_STATUSFALLTYP###',$meldung->STR_STATUSFALLTYP,$row);
            $row = str_replace('###STR_BNACHNAME###',$meldung->STR_BNACHNAME,$row);
            $row = str_replace('###STR_BVORNAME###',$meldung->STR_BVORNAME,$row);
            
            $rows .= $row;
        }
        return $rows;
    }
    
    function getUserList($template){
        $filter = '?$orderby=STR_NACHNAME';
        //echo $filter;
        $url = $this->serviceRoot.'/Nutzer'.$filter;
        $rows = '';
        
        $response = $this->fetchWithUri($url);
        foreach ($response->results as $nutzer){
            $row = $template;
            $row = str_replace('###STR_NACHNAME###',$nutzer->STR_NACHNAME,$row);
            $row = str_replace('###STR_VORNAME###',$nutzer->STR_VORNAME,$row);
            $row = str_replace('###STR_EMAIL###',$nutzer->STR_EMAIL,$row);
            
            $row = str_replace('###BASIS###',$this->translateMainRights($nutzer->TXT_RECHTE),$row);
            $row = str_replace('###ZUSATZ###',$this->translateExtraRights($nutzer->TXT_RECHTE),$row);
            
            $rows .= $row;
        }
        return $rows;
    }
    
    //Basisrechte ermitteln
    function translateMainRights($rights){
        $rights = explode('||',$rights);
        //var_dump($rights);
        if (is_array($rights)){
            //wenn der Fall abgeschlossen ist, wird immer nur lese-Rechte zurück gegeben
            if (in_array('locked', $rights)) return 'Nur Lesen';
            //Falls Basis-Rechte mehrfach vergeben wurden, sollte immer nur das höchste Recht zurück gegeben werden
            if (in_array('doc', $rights)) return 'Maßnahmen anordnen';
            if (in_array('edit', $rights)) return 'Basisdaten bearbeiten';
            return 'Nur Lesen';    
        }        
    }
    
    function translateExtraRights($rights){
        $rights = explode('||',$rights);
        $returner = array();
        
        foreach ($rights as $r){
            switch ($r) {
                case 'aZahlen':
                    $returner[] = 'Auswertungen anzeigen';
                    break;
                case 'freig':
                    $returner[] = 'Freigaben erteilen';
                    break;
                case 'ovEdit':
                    $returner[] = 'OV Informationen erfassen';
                    break;
                case 'abortQs':
                    $returner[] = 'Quarantänen abbrechen';
                    break;
                case 'admMs':
                    $returner[] = 'Maßnahmen administrieren (Löschen von Einträgen und Subeinträgen)';
                    break;
                case 'closeMs':
                    $returner[] = 'Maßnahmen abschließen';
                    break;
                case 'openMs':
                    $returner[] = 'abgeschlossene Maßnahmen wieder zur Bearbeitung öffnen';
                    break;
                case 'survnet':
                    $returner[] = 'Suvent Eintrag vermerken';
                    break;                
                case 'admUser':
                    $returner[] = 'Nutzerliste anzeigen';
                    break;                
            }            
        }
        
        
        return implode(', ',$returner);
    }
    
    function translateFeldnamen($feldname){
        switch($feldname){
            case 'STR_BNACHNAME':$feldname = "Nachname, Betroffener";break;
            case 'STR_BVORNAME': $feldname = "Vorname, Betroffener";break;
            case 'DT_BGEBURTSDATUM': $feldname = "Geburtsdatum, Betroffener";break;
            case "STR_BGESCHLECHT": $feldname = "Geschlecht, Betroffener";break;
            case "STR_BSTRASSE": $feldname = "Strasse, Betroffener";break;
            case "STR_BHAUSNUMMER": $feldname = "Hausnummer, Betroffener";break;
            case "STR_BPLZ": $feldname = "PLZ, Betroffener";break;
            case "STR_BORT": $feldname = "Ort, Betroffener";break;
            case "STR_BEMAIL": $feldname = "Email, Betroffener";break;
            case "STR_BTELEFON":  $feldname = "Telefon, Betroffener";break;
            case "STR_BTELEFON2": $feldname = "Telefon 2, Betroffener";break;
            case "STR_BAABWNAME": $feldname = "Abweichende Adresse, Name";break;
            case "STR_BASTRASSE": $feldname = "Abweichende Adresse, Strasse";break;
            case "STR_BAHAUSNUMMER": $feldname = "Abweichende Adresse, Hausnummer";break;
            case "STR_BAPLZ": $feldname = "Abweichende Adresse, PLZ";break;
            case "STR_BAORT": $feldname = "Abweichende Adresse, Ort";break;
            case "B_CRITINFRA": $feldname = "Kritische Infrastruktur";break;
            case "STR_CRITINFRATYP": $feldname = "Kritische Infrastruktur, Typ";break;
            case "DT_AUSNAHMEGENEMIGUNG": $feldname = "Datum Ausnahmegenehmigung";break;
            case "STR_BERUFBEZEICHNUNG": $feldname = "Berufsbezeichnung";break;
            case "STR_BERUFARBEITGEBER": $feldname = "Arbeitgeber";break;
            case "STR_BERUFSTRASSE": $feldname = "Arbeit, Strasse";break;
            case "STR_BERUFHAUSNUMMER": $feldname = "Arbeit, Hausnummer";break;
            case "STR_BERUFPLZ": $feldname = "Arbeit, PLZ";break;
            case "STR_BERUFORT": $feldname = "Arbeit, Ort";break;
            case "STR_BEB1VORNAME": $feldname = "Erziehungsberechtigte/r 1, Vorname";break;
            case "STR_BEB1NACHNAME": $feldname = "Erziehungsberechtigte/r 1, Nachname";break;
            case "STR_BEB2VORNAME": $feldname = "Erziehungsberechtigte/r 2, Vorname";break;
            case "STR_BEB2NACHNAME": $feldname = "Erziehungsberechtigte/r 2, Nachname";break;
            case "STR_BEBSTRASSE": $feldname = "Erziehungsberechtigte, Strasse";break;
            case "STR_BEBHAUSNUMMER": $feldname = "Erzierungsberechtigte, Hausnummer";break;
            case "STR_BEBPLZ": $feldname = "Erzierungsberechtigte, PLZ";break;
            case "STR_BEBORT": $feldname = "Erzierungsberechtigte, Ort";break;
            case "STR_INDEXFALL": $feldname = "Indexfall";break;
            case "STR_KONTAKTNAME": $feldname = "Kontakt, Name";break;
            case "DT_KONTAKTLETZTER": $feldname = "letzter Kontakt";break;
            case "STR_KONTAKTORT": $feldname = "Kontakt, Ort";break;
            case "STR_KONTAKTORTINFO": $feldname = "Kontakt, Ort Info";break;
            case "STR_KONTAKTART": $feldname = "Kontaktart";break;
            case "TXT_KONTAKTINFO": $feldname = "Kontaktinfo";break;
            case "STR_KSTRASSE": $feldname = "Kontakt, Straße";break;
            case "STR_KHAUSNUMMER": $feldname = "Kontakt, Hausnummer";break;
            case "STR_KPLZ": $feldname = "Kontakt, PLZ";break;
            case "STR_KORT": $feldname = "Kontakt, Ort";break;
            case "STR_KONTAKTKATEGORIE": $feldname = "Kontaktkategorie";break;
            case "L_KONTAKTFALL": $feldname = "Kontaktfall Nr.";break;
            case "STR_KONTAKTRISIKOGEBIET": $feldname = "Kontakt im Risikogebiet";break;
            case "DT_KONTAKTRISIKOGEBIETVON": $feldname = "Kontakt im Risikogebiet, von";break;
            case "DT_KONTAKTRISIKOGEBIETBIS": $feldname = "Kontakt im Risikogebiet, bis";break;
            case "TXT_KONTAKTPERSONEN": $feldname = "Kontaktpersonen";break;
            case "STR_KONTAKTPERHBEOB": $feldname = "Angaben als Kontaktperson, Beobachtungszustand";break;
            case "DT_SYMSTART": $feldname = "Symptome, Start";break;
            case "STR_RISIKOGRUPPEART": $feldname = "Risikogruppe, Art";break;
            case "TXT_GRUNDERKRANKUNGINFO": $feldname = "Grunderkrankung";break;
            case "TXT_KOMPLIKATIONENINFO": $feldname = "Komplikationen";break;
            case "STR_SYMFIEBERWERT": $feldname = "Symptom, Fieber";break;
            case "TXT_SYMINFO": $feldname = "Symptome, Info";break;
            case "DT_STATIONAERSTART": $feldname = "Stationäre Aufnahme, Start";break;
            case "STR_STATIONAERKRANKENHAUS": $feldname = "Stationäre Aufnahme, Krankenhaus";break;
            case "TXT_STATIONAERINFO": $feldname = "Stationäre Aufnahme, Info"; break;
            case "STR_RISSCHWANGERTRIMESTER": $feldname = "Risiko, Schwangeschaftsrimester";break;
            case "STR_STATUSFALLTYP": $feldname = "Status/Falltyp";break; 
            case "STR_STATUSFALLTYPPREV": $feldname = "Status/Falltyp, vorheriger Wert";break;
            case "TXT_NOTIZEN": $feldname = "Notizen";break;
            case "TXT_ABSTRICHNOTIZ": $feldname = "Abstrich, Notiz";break;
            case "STR_ABSTRICHTYP": $feldname = "Abstrich, Typ";break;
            case "DT_ABSTRICHZEITPUNKT": $feldname = "Abstrich, Zeitpunkt";break;
            case "STR_ABSTRICHDURCH": $feldname = "Abstrich, Durch";break;
            case "L_ABSTRICHDURCH": $feldname = "Abstrich, Durch, Nutzer-ID";break;
            case "DT_QUARANTAENEVON": $feldname = "Quarantäne, Von";break;
            case "DT_QUARANTAENEBIS": $feldname = "Quarantäne, Bis";break;
            case "STR_QUARANTAENETYP": $feldname = "Quarantäne, Typ";break;
            case "TXT_QUARANTAENEINFO": $feldname = "Quarantäne, Info";break;
            case "STR_QUARANTAENEADURCH": $feldname = "Quarantäne, Durch";break;
            case "L_QUARANTAENEADURCH": $feldname = "Quarantäne, Durch, Nutzer-ID";break;
            case "TXT_TATVERBOTNOTIZ": $feldname = "Tatverbot, Notiz";break;
            case "DT_TATVERBOTVON": $feldname = "Tatverbot, von";break;
            case "DT_TATVERBOTBIS": $feldname = "Tatverbot, bis";break;
            case "STR_TATVERBOTDURCH": $feldname = "Tatverbot, Durch";break;
            case "L_TATVERBOTDURCH": $feldname = "Tatverbot, Durch, Nutzer-ID";break;
            case "DT_VERLAUFGESUNDAM": $feldname = "Verlauf, gesund, am";break;
            case "DT_VERLAUFVERSTORBENAM": $feldname = "Verlauf, verstorben, am";break;
            case "STR_MGRUND": $feldname = "Meldegrund";break;
            case "TXT_MGRUND": $feldname = "Meldegrund";break;
            case "B_BADRESSEKONTROLLIERT": $feldname = "Adresse kontrolliert";break;
            case "B_KONTAKTERKRANKT": $feldname = "Kontakt erkrankt";break;
            case "B_BEBANDEREMELDEADRESSE": $feldname = "Abweichende Meldeadresse";break;
            case "B_SYMVORHANDEN": $feldname = "Symptome vorhanden";break;
            case "B_RISIKOGRUPPE": $feldname = "Mitglied Risikogruppe";break;
            case "B_VIRUSL": $feldname = "Lungenenzündung, Virus";break;
            case "B_SYMHUSTEN": $feldname = "Symptom, Husten";break;
            case "B_SYMSCHNUPFEN": $feldname = "Symptom, Schnupfen";break;
            case "B_SYMHALSSCHMERZEN": $feldname = "Symptom, Halsschmerzen";break;
            case "B_SYMABGESCHLAGEN": $feldname = "Symptom, Abgeschlagen";break;
            case "B_SYMFIEBER": $feldname = "Symptom, Fieber";break;
            case "B_SYMDURCHFALL": $feldname = "Symptom, Durchfall";break;
            case "B_SYMLUFTNOT": $feldname = "Symptom, Luftnot";break;
            case "B_SYMGERUCH": $feldname = "Symptom, Geruchsverlust";break;
            case "B_SYMKOPFSCHMERZEN": $feldname = "Symptom, Kopfschmerzen";break;
            case "B_SYMGLIEDERSCHMERZEN": $feldname = "Symptom, Gliederschmerzen";break;
            case "B_KOMPLIKATIONEN": $feldname = "Komplikationen";break;
            case "B_GRUNDERKRANKUNG": $feldname = "Grunderkrankung";break;
            case "B_SCHWANGERSCHAFT": $feldname = "Schwangerschaft";break;
            case "B_STATIONAER": $feldname = "Stationäre Aufnahme";break;
            case "B_BERUF": $feldname = "Berufstätig";break;
            case "B_LEGITIMATIONSBESCHEINIGUNG": $feldname = "Legitimationsbescheinigung";break;
            case "B_SYMPNEUMONIE": $feldname = "Symptom, Pneumonie";break;
            case "B_SYMARDS": $feldname = "Symptom, ARDS";break;
            case "B_SYMBEATMERKRANKUNG": $feldname = "Symptom, Beatmung";break;
            case "B_SYMDYSPNOE": $feldname = "Symptom, Dyspnoe";break;
            case "B_SYMGERUCHD": $feldname = "Symptom, Geruch";break;
            case "B_SYMGESCHMACKD": $feldname = "Symptom, Geschmack";break;
            case "B_SYMTACHYKARDIE": $feldname = "Symptom, Tachykardie";break;
            case "B_SYMTACHYPNOE": $feldname = "Symptom, Tachypnoe";break;
            case "B_SYMALLGEMEIN": $feldname = "Symptom, Allgemein";break;
            case "B_RISHERZKREISLAUF": $feldname = "Risiko, Herzkreislauf";break;
            case "B_RISDIABETES": $feldname = "Risiko, Diabetes";break;
            case "B_RISLEBER": $feldname = "Risiko, Leber";break;
            case "B_RISNEURO": $feldname = "Risiko, Neurologisch";break;
            case "B_RISIMMUN": $feldname = "Risiko, Immunität";break;
            case "B_RISNIERE": $feldname = "Risiko, Niere";break;
            case "B_RISLUNGE": $feldname = "Risiko, Lunge";break;
            case "B_RISKREBS": $feldname = "Risiko, Krebs";break;
            case "B_RISSCHWANGER": $feldname = "Risiko, Schwanger";break;
            case "B_RISPOSTPARTUM": $feldname = "Risiko, Postpartum";break;
            case "B_HYGIENEAUFKL": $feldname = "Hygieneaufklärung erfolgt";break;
            case "B_VERLAUFGESUND": $feldname = "Verlauf gesund";break;
            case "B_VERLAUFVERSTORBEN": $feldname = "Verlauf verstorben";break;
            case "B_QUARANTAENE": $feldname = "Quarantäne vorbereitet";break;
            case "B_ABSTRICH": $feldname = "Abstrich vorbereitet";break;
            case "B_ABSTRICHDRINGEND": $feldname = "Abstrich, dringend";break;
            case "B_ABSTRICHMOBIL": $feldname = "Abstrich, mobil";break;
            case "B_KONTAKTRISIKOGEBIET": $feldname = "Kontakt im Risikogebiet";break;
            case "DT_SURVNETEINGABE": $feldname = "Survneteingabe am";break;
            case "STR_KONTAKTERKRANKT": $feldname = "Kontakt erkrankt";break;
            case "B_CRITINFRAUNV": $feldname = "Unverzichtbarkeit vom Arbeitgeber angezeigt";break;
            case "STR_KONTAKTPENDPUNKT": $feldname = "Status als Kontaktperson";break;
        }
        
        return $feldname;
    }
    
    function getSchichten(){
        $url = $this->serviceRootEp.'/Schicht?$expand=Besetzung';
        $response = $this->fetchWithUri($url);
        
        return $response->results;
    }
    
    function getPlanForDay($timestamp, $freigabe = false){
        date_default_timezone_set('UTC');
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $datestring = $date->format('Y-m-d H:i:s');
        $datestring = str_replace(' ','T',$datestring);
        $filter = 'DT_DATUM%20eq%20datetime%27'.$datestring.'%27';
        if (!$freigabe){
            $filter .= '%20and%20DT_FREIGABEAM%20ne%20null';
        }
        
        $url = $this->serviceRootEp.'/Plan?$expand=Nutzer,Schicht,NutzerMeldung&$filter='.$filter;
        $response = $this->fetchWithUri($url);
        return $response->results;
    }
    
    function saveRFreigaben($data){
        //var_dump($data);
        $timestamp = mktime( 0, 0, 0, 1, 1,  $data['year'] ) + ( ($data['week']-1) * 7 * 24 * 60 * 60 );  
        $calendar = new Calendar();
        $days = $calendar->getDaysForWeekView($timestamp);
        foreach ($days as $day){
            $plaene = $this->getPlanForDay($day['timestamp'], true);
            foreach ($plaene as $plan){
                $values = array();
                $values['DT_FREIGABEAM'] = date('d.m.Y');
                $values['L_FREIGABEDURCH'] = $this->user['uid'];
                $values['STR_FREIGABEDURCH'] = $this->user['nachname'].', '.$this->user['vorname'];
                $values = $this->processSaveData($values);
                $update = \Httpful\Request::put($this->serviceRootEp.'/Plan('.$plan->LID.')')
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();       
                var_dump($plan->LID);
                var_dump($values);
            }            
        }
    }
}

