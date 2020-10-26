$(document).ready(function(){
    //checkQuarantaene();
    //checkQuarantaeneVerl();
    //checkQuarantaeneVerl2();
    //Quarantäne verlängerung muss noch readonly werden, wenn es einmal durch den job gelaufen ist
    //checkAbstriche();
        
    $(':checkbox[readonly="readonly"]').click(function() {
        return false;
    });
    
    $('.rueckSelect').change(function(){
        //alert($(this).val());
        if ($(this).val() == 'positiv'){
            if ($('#STR_STATUSFALLTYPPREV').val() == "") $('#STR_STATUSFALLTYPPREV').val($('#STR_STATUSFALLTYP').val());
            $('#STR_STATUSFALLTYP').val("BF");            
        }
    })    
});

function setAbstrichDurch(){
    if ($("#B_ABSTRICH").prop('checked')){
        $("#STR_ABSTRICHDURCH").val($("#logged_in_name").html());
    } else {
        $("#STR_ABSTRICHDURCH").val("");
    }
}

function setQuarantaeneDurch(){
    checkQuarantaene();

    if ($("#B_QUARANTAENE").prop('checked')){
        $("#STR_QUARANTAENEADURCH").val($("#logged_in_name").html());
    } else {
        $("#STR_QUARANTAENEADURCH").val("");
    }
}

function setTatverbotDurch(){
    checkTatverbot();
    
    if ($("#B_TATVERBOT").prop('checked')){
        $("#STR_TATVERBOTDURCH").val($("#logged_in_name").html());
    } else {
        $("#STR_TATVERBOTDURCH").val("");
    }
}

function addNewQuar(){
    var url = "src/data/addNewQ.php";
    
    if ($('#new_quar_bis').val() != '' && $('#new_quar_typ').val() != ''){    
        var data = {DT_ANGEORDNETAM:$('#new_quar_am').val(),DT_ANGEORDNETBIS:$('#new_quar_bis').val(),STR_TYP:$('#new_quar_typ').val(),STR_VERANLASSTDURCH:$('#new_quar_durch').val(),TXT_NOTIZEN:$('#new_quar_notizen').val(),FKLID:$('#new_quar_fklid').val()};

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
        reload_quars();
    } else {
        var modal = $('#modalError');
        //modal.find('.modal-title').text('Speichern');
        modal.find('.modal-body').text('Um eine Quarantäne nächträglich zu erfassen werden mindestens ein Enddatum und ein Typ benötigt!');
        modal.modal();
        return false;
    }
}