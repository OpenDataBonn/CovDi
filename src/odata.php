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
    
    private $rights = '';
    
    function __construct() {
        //basics müssen als Global definiert werden
        global $basics;
        $this->basics =& $basics;
        
        $this->serviceRoot = $basics['serviceRoot'];
        $this->serviceRootDienste = $basics['serviceRootDienste'];
        
        if (isset($_SESSION[$basics['session']]['user'])){
            $this->rights = $_SESSION[$basics['session']]['user']['rechte'];            
        }        
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
        //echo $url;
        $result = $this->fetchWithUri($url);
        
        $single = file_get_contents("templates/blocks/weblog.html"); 
        $log = "";
        
        if ($result){
            foreach ($result->results as $rkey => $rvals){
                $data = $this->processSingle($rvals); 
                $slog = $single;
                foreach ($data as $key => $value){
                    $slog = str_replace($key,$value,$slog);
                }   
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
        $url = $this->serviceRoot.'/Meldungen('.$id.')';
        $result = $this->fetchWithUri($url);
        
        if ($result->B_ABGESCHLOSSEN == true) return true;
        else return false;
        //    var_dump($result->B_ABGESCHLOSSEN);
    }
    
    function checkDouble($data){
        //var_dump($data);
        $data['gebDatum'] = str_replace('.','%2E',$data['gebDatum']);
        $url = $this->serviceRootDienste."/checkDouble?nachname='".$data['nachname']."'&vorname='".$data['vorname']."'&gebDatum='".$data['gebDatum']."'";
        //echo $url;
        $result = $this->fetchWithUri($url);
        
        if ($result) return $result->checkDouble;
        else return false;
    }
    
    function checkPin($pin){
        $url = $this->serviceRoot.'/Nutzer?$filter=STR_PIN%20eq%20'.$pin;
        $result = $this->fetchWithUri($url);
        
        if (count($result->results) >0){
            $rights = explode('||',$result->results[0]->TXT_RECHTE);
            $login = array(
                'uid'       => $result->results[0]->LID,
                'vorname'   => $result->results[0]->STR_VORNAME,
                'nachname'  => $result->results[0]->STR_NACHNAME,
                'rechte'    => $rights,
                'rechte_alt' => $result->results[0]->STR_ZUGRIFFSART
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
    
    function getSingle($id, $template, $atemplate, $qtemplate, $ttemplate, $bttemplate = ""){
        
        $url = $this->serviceRoot.'/Meldungen('.$id.')?$expand=Abstriche,Quarantaenen,Tatverbote,Notizen,Bluttests';
        $result = $this->fetchWithUri($url); 
        //var_dump($result);
        
        if ($result){   
            $abs = "";
            $qus = "";
            $tvs = "";
            $ons = "";
            $bts = "";
            $vars = $this->processSingle($result);
            $today =  new \DateTime();
            $vars['###ACT_DATUM###'] = $today->format('d.m.Y');
            $vars['###ACT_USER###'] = '';
            $vars['###ACT_USER_ID###'] = '';
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
            //var_dump($abs);   
            $deleteFallButton = true;
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
                                
                                if (in_array('admMs',$this->rights)){
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
                                
                                if (in_array('admMs',$this->rights)){
                                    $quar['###ADMMS###'] = '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteQuarantaene('.$quar['###LID###'].')">Löschen</button>';
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
                                if (in_array('admMs',$this->rights)){
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
                                    if (in_array('admMs',$this->rights)){
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
                                if (in_array('admMs',$this->rights)){
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
                    }
                }
            }
            if ($deleteFallButton){
                if (in_array('admMs',$this->rights)){
                    $template = str_replace('###ADMMS###', '<button class="btn btn-outline-danger btn-sm" type="button" onclick="askDeleteFall('.$vars['###LID###'].')">Löschen</button>', $template);
                } else {
                    $template = str_replace('###ADMMS###', '', $template);
                }
            } else {
                $template = str_replace('###ADMMS###', '', $template);
            }
            //var_dump($abs);
            $template = str_replace('###ABSTRICHE###',$abs,$template);
            $template = str_replace('###QUARANTAENEN###',$qus,$template);
            $template = str_replace('###TATVERBOTE###',$tvs,$template);
            $template = str_replace('###OLDNOTES###',$ons,$template);
            $template = str_replace('###BLUTTESTS###',$bts,$template);
            
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
    }
    
    function saveBluttests($tests){
        var_dump($tests);
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
            $new = $qs['new'];
            $values = $this->processSaveData($new);
            if($values['DT_ANGEORDNETBIS'] != "" && $values['STR_TYP'] != ""){
                //var_dump($values);
                $insert = \Httpful\Request::post($this->serviceRoot.'/Quarantaenen')
                    //->body('{"STR_TITEL_6A585D30":"wutt?","L_ZAHL":5}')
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();   
            }
            unset($qs['new']);
        }
        foreach ($qs as $id => $data){
            $values = $this->processSaveData($data);
            //var_dump($values);
            if (in_array('doc',$this->rights) || in_array('edit',$this->rights)){
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
    
    function saveSingle($data){
        $values = array();
        $values = $this->processSaveData($data);        
        $values = $this->setSingleBooleans($values);
        
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
                      'B_SCHWANGERSCHAFT','B_STATIONAER','B_STATIONAER','B_BERUF','B_LEGITIMATIONSBESCHEINIGUNG');
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
                echo $datestring.'<br >';
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
                $filter .= '(L_LASTEDIT%20eq%20null)%20and%20(STR_TRANSID%20ne%20null)'; 
                break;
        }
        
        if ($filter != '$filter=') {
            if ($searchString != "") $searchString .= "&";
            $searchString .= $filter;
            
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
        $url = $this->serviceRoot.'/Meldungen'.$this->getMeldungenSearchString($search, $type, $page, $pageSize);
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
        //$value = $value / 1000;
        //$value = (float)$value;
        $date = "";
        //echo $value .'<br />';
        if ($value > 0 && strlen($value) > 10){
            $value = substr($value,0,10);
        }
        if ($value != 0) {
            $dt = new \DateTime("@$value", $local);
            if ($type == 'utc') $dt->setTimeZone($utc);
            else $dt->setTimeZone($local);
            //$dt->add(new \DateInterval('PT1H'));
            $date = $dt->format($format);
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
                case 'fall':
                    $delete = \Httpful\Request::delete($this->serviceRoot.'/Meldungen('.$id.')')
                        ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                        ->sendsJson()
                        ->send(); 
                    break;
            }
        }
        
        return $delete;
    }
}

