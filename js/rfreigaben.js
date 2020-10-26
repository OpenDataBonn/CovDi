function prevWeek(week,year){
    location.href = '?type=freigabeR&week='+(week-1)+'&year='+year; 
}

function nextWeek(week,year){
    location.href = '?type=freigabeR&week='+(week+1)+'&year='+year; 
}

function freigabeRoster(week, year){
    var url = "src/freigabe/rfreigabe.php";
    var data = {week:week,year:year};
    $.ajax({
            type: "POST",
            url: url,
            data: data, 
            success: function(saved)
            {
               if (saved){
                   var modal = $('#modalInfo');
                   //modal.find('.modal-title').text('Speichern');
                   modal.find('.modal-body').text('Die Daten wurden erfolgreich gespeichert');
                   modal.modal();
               } else {
                   var modal = $('#modalError');
                   //modal.find('.modal-title').text('Speichern');
                   modal.find('.modal-body').text('Fehler beim speichern!');
                   modal.modal();
               }
            }
        });
}
