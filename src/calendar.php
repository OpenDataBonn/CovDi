<?php
namespace base;

class Calendar {
    private $base_cal = CAL_GREGORIAN;
    private $schichten;
    
    function __construct(){
        $oData = new OData();
        $this->schichten = $oData->getSchichten();
    }
    
    function daysInMonth($month, $year){
        return cal_days_in_month($this->base_cal, $month, $year);
    }
    
    function getDay($timestamp){
        $act_weekday = date('w',$timestamp);
        $act_month = date('m',$timestamp);
        $current_date = date("Y-m-d", $timestamp);
            
        $day = array();
        $day['timestamp'] = strtotime($current_date);
        $day['datestring'] = date('d.m.Y',$day['timestamp']);
        $day['weekday'] = $act_weekday;
        if ($act_month == date('m',$day['timestamp'])) $day['act_month'] = true;
        else $day['act_month'] = false;
        $day['schichten'] = $this->getSchichtenForDay($day['timestamp']);
        
        return $day;
    }
    
    function getDaysForWeekView($timestamp){
        $act_weekday = date('w',$timestamp);
        $act_month = date('m',$timestamp);
        $week_start = date('m-d-Y', strtotime('-'.($act_weekday-1).' days', $timestamp));
        $week_end = date('m-d-Y', strtotime('+'.(6-$act_weekday+1).' days', $timestamp));
        
        $days = array();
        $start_tstamp = strtotime('-'.($act_weekday-1).' days', $timestamp);
        
        $count_weekday = 1;
        while ($count_weekday <= 7){            
            $current_date = date("Y-m-d", strtotime("+".($count_weekday-1)." days", $start_tstamp));
            
            $day = array();
            $day['timestamp'] = strtotime($current_date);
            $day['datestring'] = date('d.m.Y',$day['timestamp']);
            $day['weekday'] = $count_weekday;
            if ($act_month == date('m',$day['timestamp'])) $day['act_month'] = true;
            else $day['act_month'] = false;
            $day['schichten'] = $this->getSchichtenForDay($day['timestamp']);
            $days[] = $day;
            
            $count_weekday ++;            
        }
        
        return $days;
    }
    
    function getDaysForMonthView($month, $year){
        $first_string = $year.'-'.$month.'-01';
        //echo $first_string.'<br />';
        $first = new \DateTime($first_string);
        $first_weekday = $first->format('w');
        $days = array();
        
        //echo ($first_weekday);
        if ($first_weekday == 0) $first_weekday = 7;
        $current_weekday = 1;
        while ($first_weekday > $current_weekday){
            $substract = $first_weekday - $current_weekday;
            $current_date = date("Y-m-d", strtotime("-".$substract." days", strtotime($first_string)));
            
            $day = array();
            $day['timestamp'] = strtotime($current_date);
            $day['datestring'] = date('d.m.Y',$day['timestamp']);
            $day['weekday'] = $current_weekday;
            $day['act_month'] = false;
            $day['schichten'] = $this->getSchichtenForDay($day['timestamp']);
            $days[] = $day;
            
            $current_weekday ++;
        }
        
        $current_day = 1;
        while ($current_day <= $this->daysInMonth($month, $year)){
            $current_string = $year.'-'.$month.'-'.$current_day;
            $current_date = date("Y-m-d", strtotime($current_string));
            $cd = new \DateTime($current_string);
                    
            $day = array();
            $day['timestamp'] = strtotime($current_date);
            $day['datestring'] = date('d.m.Y',$day['timestamp']);
            $day['weekday'] = $cd->format('w');
            $day['act_month'] = true;
            $day['schichten'] = $this->getSchichtenForDay($day['timestamp']);
            $days[] = $day;
            
            $current_day ++;
        }
        
        $last_string = $year.'-'.$month.'-'.$this->daysInMonth($month, $year);
        $last = new \DateTime($last_string);
        $last_weekday = $last->format('w');
        
        $current_weekday = $last_weekday;
        $adder = 1;
        $adiff = 7 - $current_weekday;
        while ($adder <= $adiff){
            $current_date = date("Y-m-d", strtotime("+".($adder)." days", strtotime($last_string)));
            
            $day = array();
            $day['timestamp'] = strtotime($current_date);            
            $day['datestring'] = date('d.m.Y',$day['timestamp']);
            $day['weekday'] = $last_weekday + $adder;
            $day['act_month'] = false;
            $day['schichten'] = $this->getSchichtenForDay($day['timestamp']);
            $days[] = $day;
            
            $adder ++;
        }
        return $days;
    }
    
    function getSchichtenForDay($day){
        $schichten = array();
        foreach ($this->schichten as $schicht){
            if ($schicht->DT_STARTDATUM == null ){
                $vars = json_decode(json_encode($schicht), true);
                $schichten[$schicht->STR_TITEL] = $vars;    
            }    
        }
        return $schichten;
    }
}
?>