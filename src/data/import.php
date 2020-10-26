<?php
/**
URL-Ziel zum löschen von Meldungen oder Unterdatensätzen. Bei Fehler kommt eine ensprechende Meldung, damit der Nutzer merkt das was schieff gelaufen ist und den Admin kontaktieren kann.
Hilft vor allem bei der Fehlersuche
*/
include "../../includes.php";
global $basics; 

$oData = new \base\OData;     

$response = "";
$importType = $_POST['importType'];
$importState = $_POST['importState'];
switch ($importType){
    case 'meldungen':
        $kennung = $_POST['kennung'];
        $statusfalltyp = $_POST['statusfalltyp'];
        $kontaktort = $_POST['kontaktort'];
        $kontakterkrankt = $_POST['kontakterkrankt'];
        $kontaktname = $_POST['kontaktname'];
        $afall = $_POST['aFall'];
        if ($importState == "simulation"){            
            $importFile = file_get_contents("../../templates/import/review/importM.html");
            $lineFile = file_get_contents("../../templates/import/review/importMLine.html");

            if (count($_FILES) > 0){
                //var_dump($_FILES);      
                if ($_FILES["importMs"]["error"] == 0){
                    $dataHandle = fopen($_FILES["importMs"]["tmp_name"],"r");
                    if ($dataHandle){
                        $lineCounter = 0;
                        $lines = "";
                        while(($buffer = fgets($dataHandle, 4096)) !== false){
                            if ($lineCounter > 0 && trim($buffer) != ";;;;;;;;;;;;;;"){
                                //echo (mb_detect_encoding($buffer));
                                $buffer = mb_convert_encoding($buffer, "UTF-8", "ISO-8859-1");
                                $line = explode(";",$buffer);                                
                                $isDouble = $oData->checkDouble(array("nachname" => $line[0], "vorname" => $line[1], "gebDatum" => $line[3]));
                                //echo $isDouble.'<br/>';
                                if ($isDouble){
                                    $lines .= "<tr><td colspan='100%'>Name, Vorname und Geburtstag (".$line[0].", ".$line[1].", ".$line[3].") stimmen mit den Daten folgender Fälle überein: ".$isDouble.'</td></tr>';
                                } else {
                                    $line_output = $lineFile;                                    
                                    $line_marker = array();
                                    $line_marker['###COUNTER###'] = $lineCounter;

                                    $line_marker['###NACHNAME###'] = $line[0];
                                    $line_marker['###VORNAME###'] = $line[1];
                                    $line_marker['###GESCHLECHT###'] = $line[2];
                                    $line_marker['###GEBDATUM###'] = $line[3];
                                    $line_marker['###STRASSE###'] = $line[4];
                                    $line_marker['###HNR###'] = $line[5];
                                    $line_marker['###PLZ###'] = $line[6];
                                    $line_marker['###ORT###'] = $line[7];
                                    $line_marker['###MOBIL###'] = $line[8];
                                    $line_marker['###NAMEEINR###'] = $line[9];
                                    $line_marker['###LKONTAKT###'] = $line[10];
                                    $line_marker['###EB1NN###'] = $line[11];
                                    $line_marker['###EB1VN###'] = $line[12];
                                    $line_marker['###EB2NN###'] = $line[13];
                                    $line_marker['###EB2VN###'] = $line[14];
                                    if (count($line)>15) $line_marker['###TXT_KONTAKTINFO###'] = $line[15];

                                    foreach ($line_marker as $marker => $value){
                                        $line_output = str_replace($marker, $value, $line_output);
                                    }
                                    $lines .= $line_output;
                                }
                            }
                            $lineCounter ++;                            
                        }
                        $response = str_replace("###".$statusfalltyp."###","selected",$importFile); 
                        $response = str_replace("###".$kontaktort."###","selected",$response);
                        $response = str_replace("###".$kontakterkrankt."###","selected",$response);
                        $response = str_replace("###NAMEKONTAKT###",$kontaktname,$response); 
                        $response = str_replace("###KENNUNG###",$kennung,$response); 
                        $response = str_replace("###AFALL###",$afall,$response);
                        $response = str_replace("###ROWS###",$lines,$response);     
                        $response = preg_replace('/###[\s\S]+?###/','',$response);
                    } else {
                        $response = "Es gibt einen Fehler beim Dateiupload";    
                    }
                } else {
                    $response = "Es gibt einen Fehler beim Dateiupload";
                }
            } else {
                $response = "Sie haben keine Importdatei ausgewählt.";
            }
        } elseif ($importState == "import"){
            $lines = $_POST['line'];
            $response = "Folgende Fallnummern wurden angelegt: <br />";
            $links = "";
            $only_numbers = "";
            if (is_array($lines)){
                foreach ($lines as $line){   
                    $line['STR_INDEXFALL'] = $kennung;
                    $line['STR_KONTAKTORT'] = $kontaktort;
                    $line['L_KONTAKTFALL'] = $afall;
                    $line['STR_STATUSFALLTYP'] = $statusfalltyp;
                    $line['STR_KONTAKTERKRANKT'] = $kontakterkrankt;
                    $line['STR_KONTAKTNAME'] = $kontaktname;
                    $line['STR_BERATERNAME'] = "Datenimport durch: ".$_SESSION[$basics['session']]['user']['nachname'].', '.$_SESSION[$basics['session']]['user']['vorname'];
                    //echo "<br />";
                    $addresponse = $oData->addSingle($line);
                    //var_dump($addresponse);
                    $new_id = str_replace('Meldungen(','',$addresponse->body->link['href']);
                    $new_id = str_replace(')','',$new_id);
                    //var_dump($line);
                    if (!$addresponse->hasErrors()){
                        if ($only_numbers != "") $only_numbers .= ", ";
                        $only_numbers .= $new_id;
                        $links .= '<a class="btn btn-info btn-sm" target="_blank" href="?type=single&id='.$new_id.'" role="button" >'.$new_id.'</a>&nbsp;';
                    }
                }      
            } 
            $response .= "Liste der Nummern: ".$only_numbers."<br />Direkte Links: ".$links."<br />";
        } else {
            $response = "Unbekannter Importstatus";
        }         
        break;
    default:
            $response = "Unbekannter Importtyp";
        break;
}
echo $response
?>