$(document).ready(function(){
    $(".preval_select").each(function() { 
        //console.log($(this).data('preval'));
        $(this).val($.trim($(this).data('preval'))); 
    });
    
    $('a[data-toggle="tab"]').on('show.bs.tab', function(e) {
        localStorage.setItem('activeTab', $(e.target).attr('href'));
    });
    var activeTab = localStorage.getItem('activeTab');
    if(activeTab){
        $('#myTab a[href="' + activeTab + '"]').tab('show');
    }
});

$('.table > tbody > tr > td.clickable').click(function() {
    if ($(this).parent().data("id")) location.href = '?type=single&id='+$(this).parent().data("id");
});

$('#setPin').keydown( function(e)
{
    if(e.keyCode == 13) setPin();
});
$('#goToCase').keydown( function(e)
{
    if(e.keyCode == 13) jumpToCase();
});
$('#goToPage').keydown( function(e)
{
    if(e.keyCode == 13) jumpToPage();
});

function jumpToPage(){
    var jumpTo = '?type=all&tpage='+$('#goToPage').val();
    if ($("#closed").prop('checked')) jumpTo = jumpTo + "&closed=1";
    location.href = jumpTo;
}

function jumpToCase(){
    location.href = '?type=single&id='+$('#goToCase').val();
}

function setPin(){
    var pin = $("#setPin").val();
    $.ajax({
        type: 'POST',
        url: 'src/functions/login.php',
        data: {pin: pin}
    }).done(function(data) {
        if (data != false){
            var obj = jQuery.parseJSON(data);
            if (obj.uid != null){
                location.reload();
            } else {
                alert("Falsche PIN, bitte erneut versuchen");    
            }
        } else {
            alert("Falsche PIN, bitte erneut versuchen");
        }
    }); 
}

function getPin(){
    var email = $("#getPin").val();
    $.ajax({
        type: 'POST',
        url: 'src/functions/getpin.php',
        data: {email: email}
    })
}

function unsetPin(){
    $.ajax({
        type: 'POST',
        url: 'src/functions/logout.php'
    }).done(function(){
        location.href = '?type=main';
    });
}

function calculate_age(dob) { 
    var diff_ms = Date.now() - dob.getTime();
    var age_dt = new Date(diff_ms); 
  
    return Math.abs(age_dt.getUTCFullYear() - 1970);
}

function isValidDate(dateString) {
  var regEx = /^\d{2}.\d{2}.\d{4}$/;
  if(!dateString.match(regEx)) return false;  // Invalid format
  /*var d = new Date(dateString);
  var dNum = d.getTime();
  if(!dNum && dNum !== 0) return false; // NaN value, Invalid date
  return d.toISOString().slice(0,10) === dateString;*/
  return true;
}

function askOpenFall(id){
    var modal = $('#modalClose');
    modal.find('.modal-title').text('Fall öffnen');
    modal.find('.modal-body').text('Möchten Sie den Fall wirklich wieder öffnen? Bitte prüfen Sie, dass sie damit keinen Doppelten Fall für die gleiche Person öffnen!');
    modal.find('#itemToClose').text(id);
    modal.find('#typeToClose').text('open');
    modal.modal();
}

function askCloseFall(id){
    var modal = $('#modalClose');
    modal.find('.modal-title').text('Fall schliessen');
    modal.find('.modal-body').text('Möchten Sie den Fall wirklich abschließen?');
    modal.find('#itemToClose').text(id);
    modal.find('#typeToClose').text('close');
    modal.modal();
}

function openCloseItem(){
    var id = $('#itemToClose').text();
    var type = $('#typeToClose').text();
    //alert(id);
    var url = "src/data/openclose.php";
    $.ajax({
        type: "POST",
        url: url,
        data: {type: type,id: id}, 
        success: function(deleted)
        {           
           window.location.reload();
        }        
    });
    
    var modal = $('#modalClose');
    modal.find('#itemTo').text(-1);    
    modal.find('#typeTo').text("");    
    modal.modal('toggle');
}

function setOVdurch(nr){
    if ($("#B_OVERLASSEN"+nr).prop('checked')){
        $("#DT_OVERLASSEN"+nr).val($.datepicker.formatDate('dd.mm.yy', new Date()));
        $("#STR_OVERLASSEN"+nr).val($("#logged_in_name").html());
    } else {
        $("#STR_OVERLASSEN"+nr).val("");
        $("#DT_OVERLASSEN"+nr).val("");
    }
}

function ovanordnung_eintrag(qLid){
    var url = "src/functions/ovanordnung.php";
    
    var data = {LID:qLid,B_OVERLASSEN:$('#B_OVERLASSEN'+qLid).val(),DT_OVERLASSEN:$('#DT_OVERLASSEN'+qLid).val(),STR_OVERLASSEN:$('#STR_OVERLASSEN'+qLid).val()};
    
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

function quarantaene_freigeben(user_id, user_string, qid){
    var data = {
        user_id:        user_id,
        user_string:    user_string,
        qfreigaben:     {
                            0:  {
                                lid:          qid,
                                freigabe:     'on'
                            }
                        }
    };
    var url = "src/freigabe/qfreigabe.php";

    $.ajax({
        type: "POST",
        url: url,
        data: data,
        success: function(saved)
        {
           //location.reload();               
            var modal = $('#modalInfo');
           //modal.find('.modal-title').text('Speichern');
           modal.find('.modal-body').text('Die Freigabe wurde gespeichert, die Anzeige erfolgt erst nach erneutem Laden des Falls.');
           modal.modal();
        }
    });
}