function prevWeek(week,year){
    location.href = '?type=ePlanung&week='+(week-1)+'&year='+year; 
}

function nextWeek(week,year){
    location.href = '?type=ePlanung&week='+(week+1)+'&year='+year; 
}