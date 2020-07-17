$(document).ready(function(){
    $(".preval_select").each(function() { 
        //console.log($(this).data('preval'));
        $(this).val($.trim($(this).data('preval'))); 
    });
    
    $('.datepicker').datepicker({
        dateFormat: 'dd.mm.yy',
        numberOfMonths: 2
    });
    
    $("#DT_BGEBURTSDATUM").focusout(function(){
        if (!isValidDate(($("#DT_BGEBURTSDATUM").val()))){
            var modal = $('#modalError');
            //modal.find('.modal-title').text('Speichern');
            modal.find('.modal-body').text('Bitte geben Sie ein Datum im Format: tt.mm.jjjj (Beisp: 05.01.1955) ein.');
            modal.modal();
            $("#DT_BGEBURTSDATUM").focus();
        }
    });
    
    $("#main_form").submit(function(e) {
        e.preventDefault(); // avoid to execute the actual submit of the form.
        //console.log('test2');
        var save = true;
        //Quaratänen müssen ein von + bis datum haben
        if ($("#B_QUARANTAENE").prop('checked')){
            if ($('#DT_QUARANTAENEVON').val() == "" || $('#DT_QUARANTAENEBIS').val() == "" ){
                var modal = $('#modalError');
                //modal.find('.modal-title').text('Speichern');
                modal.find('.modal-body').text('Für eine Quarantäne müssen sowohl das Datum der mündlichen Aussprache, als auch der letzte Tag der Quarantäne eingetragen werden!');
                modal.modal();
                save = false;
                return false;
            }
        } 
        //Tatverbote müssen ein von + bis datum haben
        if ($("#B_TATVERBOT").prop('checked')){
            if ($('#DT_TATVERBOTVON').val() == "" || $('#DT_TATVERBOTBIS').val() == "" ){
                var modal = $('#modalError');
                //modal.find('.modal-title').text('Speichern');
                modal.find('.modal-body').text('Für ein Tätigkeitsverbot müssen sowohl das Datum der mündlichen Aussprache, als auch der letzte Tag des Tätigkeitsverbotes eingetragen werden!');
                modal.modal();
                save = false;
                return false;
            }
        } 
        if (!checkMandatoryFields()){
            var modal = $('#modalError');
            //modal.find('.modal-title').text('Speichern');
            modal.find('.modal-body').text('Es müssen alle Pflichtfelder (mit * gekennzeichnet) ausgefüllt sein!');
            modal.modal();
            save = false;
            return false;
        }
        if (save){
            var form = $(this);
            var url = "src/data/save.php";

            $.ajax({
                type: "POST",
                url: url,
                data: form.serialize(), // serializes the form's elements.
                success: function(saved)
                {
                   if (saved){
                       reload_notes();
                       reload_abstriche();
                       reload_bluttests();
                       reload_quars();
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
    });
    
    $("#add_new").submit(function(e) {        
        e.preventDefault(); // avoid to execute the actual submit of the form.
        var save = true;
        //Wir brauchen zumindest einen Nachnamen
        if (!checkMandatoryFields()){
            var modal = $('#modalError');
            //modal.find('.modal-title').text('Speichern');
            modal.find('.modal-body').text('Es müssen alle Pflichtfelder (mit * gekennzeichnet) ausgefüllt sein, damit wir den Fall anlegen können!');
            modal.modal();
            save = false;
            return false;
        } 
          
        if (hasDouble($(this))){
            return false;
        }
        
    });
    
    checkEB();
    checkEBMeldeadresse();
    checkAufenthalt();
    checkCritInfra();
    checkQuarantaene();
    checkTatverbot();
});

function hasDouble(form){
    var url = "src/functions/checkDouble.php";
    
    var data = {nachname:$('#STR_BNACHNAME').val(),vorname:$('#STR_BVORNAME').val(),gebDatum:$('#DT_BGEBURTSDATUM').val()};
    var hasDouble = false;
    
    var request = $.ajax({
        type: "POST",
        url: url,
        data: data, 
        async: false        
        }).done(function(ids)
        {
           if (ids != ""){
               var modal = $('#modalInfo');
               //modal.find('.modal-title').text('Speichern');
               modal.find('.modal-body').text('Es wurden Einträge mit gleichem Nachnamen, Vornamen und Geburtsdatum gefunden: '+ids);
               modal.modal();
               hasDouble =  true;
               return true;
           } else {
               
               var add_url = "src/data/add.php";

               $.ajax({
                    type: "POST",
                    url: add_url,
                    data: form.serialize(), // serializes the form's elements.
                    success: function(saved)
                    {
                       if (saved != false) {
                           window.location.href = "?type=single&id="+saved;
                       } else {
                           var modal = $('#modalError');
                           //modal.find('.modal-title').text('Speichern');
                           modal.find('.modal-body').text('Fehler beim speichern!');
                           modal.modal();
                       }
                    }
               });
            }    
        });
}

function checkMandatoryFields(){
    var all = false;
    if ($.trim($("#STR_BNACHNAME").val()) != "" && $.trim($("#STR_BVORNAME").val()) != ""){
        all = true;
    }
    
    return all;
}

function reload_quars(){
    var bis = $("#new_quar_bis").val();
    var typ = $("#new_quar_typ").val();   
    if (bis != "" && typ !=""){        
        var am = $("#new_quar_am").val();
        var bis = $("#new_quar_bis").val();
        var durch = $("#new_quar_durch").val();
        var notizen = $("#new_quar_notizen").val();
        
        $.get('./templates/blocks/quarantaene.html', function (response) {
            new_abstrich = response.replace('###STR_TYP###',typ);
            new_abstrich = new_abstrich.replace('###DT_ANGEORDNETAM###',am);
            new_abstrich = new_abstrich.replace('###DT_ANGEORDNETBIS###',bis);
            new_abstrich = new_abstrich.replace('###STR_VERANLASSTDURCH###',durch);
            new_abstrich = new_abstrich.replace('###TXT_NOTIZEN###',notizen);
            //leere Felder
            new_abstrich = new_abstrich.replace('###DT_VERARBEITETAM###',"");
            new_abstrich = new_abstrich.replace('###OV_INFOS###',"");
            
            new_abstrich = new_abstrich.replace('###counter###',"");
            new_abstrich = new_abstrich.replace('###counter###',"Vorschau (Fall neu laden für normale Ansicht)");
            
            $('#q_boxes').append(new_abstrich);
        });
        
        $("#new_quar_bis").val("");
        $("#new_quar_am").val("");
        $("#new_quar_bis").val("");
        $("#new_quar_durch").val("");
        $("#new_quar_notizen").val("");
    }
}

function reload_bluttests(){
    var rueck_am = $("#new_bluttest_rueckam").val();
    if (rueck_am != ""){
        var new_test;
        var rueck = $("#new_bluttest_rueck").val();        
        var binformiert ="nein";
        if ($("#B_BINFORMIERT0BT").prop('checked')==true){
            binformiert ="ja";
        }
        var iga ="nein";
        if ($("#new_bluttest_iga").prop('checked')==true){
            iga ="ja";
        }
        var igg ="nein";
        if ($("#new_bluttest_igg").prop('checked')==true){
            igg ="ja";
        }
        var igm ="nein";
        if ($("#new_bluttest_igm").prop('checked')==true){
            igm ="ja";
        }
        var binf_am = $("#DT_BINFORMIERT0BT").val();
        var binf_durch = $("#STR_BINFORMIERT0BT").val();
        var durch = $("#new_bluttest_durch").val();
        
        $.get('./templates/blocks/bluttest.html', function (response) {
            new_abstrich = response.replace('###STR_RUECK###',rueck);
            new_abstrich = new_abstrich.replace('###DT_RUECKAM###',rueck_am);
            new_abstrich = new_abstrich.replace('###B_BINFORMIERT###',binformiert);
            new_abstrich = new_abstrich.replace('###DT_BINFORMIERTAM###',binf_am);
            new_abstrich = new_abstrich.replace('###STR_BINFORMIERTDURCH###',binf_durch);
            new_abstrich = new_abstrich.replace('###STR_VERANLASSTDURCH###',durch);
            new_abstrich = new_abstrich.replace('###B_IGA###',iga);
            new_abstrich = new_abstrich.replace('###B_IGG###',igg);
            new_abstrich = new_abstrich.replace('###B_IGM###',igm);
            //leere Felder
            new_abstrich = new_abstrich.replace('###counter###',"");
            new_abstrich = new_abstrich.replace('###counter###',"Vorschau (Fall neu laden für normale Ansicht)");
            
            $('#bt_boxes').append(new_abstrich);
        });
        
        $("#new_bluttest_rueck").val("");
        $("#new_bluttest_rueckam").val("");
        $("#B_BINFORMIERT0BT").prop("checked",false);
        $("#new_bluttest_iga").prop("checked",false);
        $("#new_bluttest_igg").prop("checked",false);
        $("#new_bluttest_igm").prop("checked",false);
        $("#DT_BINFORMIERT0BT").val("");
        $("#STR_BINFORMIERT0BT").val("");
        $("#new_bluttest_durch").val("");
    }
}

function reload_abstriche(){
    var typ = $("#new_abstrich_typ").val();
    if (typ != ""){
        var new_abstrich;
        var zeitpunkt = $("#new_abstrich_zeitpunkt").val();
        var rueck = $("#new_abstrich_rueck").val();
        var rueck_am = $("#new_abstrich_rueckam").val();
        var binformiert ="nein";
        if ($("#B_BINFORMIERT0").prop('checked')==true){
            binformiert ="ja";
        }
        var binf_am = $("#DT_BINFORMIERT0").val();
        var binf_durch = $("#STR_BINFORMIERT0").val();
        var durch = $("#new_abstrich_durch").val();
        
        $.get('./templates/blocks/abstrich.html', function (response) {
            new_abstrich = response.replace('###STR_TYP_TEXT###',typ);
            new_abstrich = new_abstrich.replace('###DT_ZEITPUNKT###',zeitpunkt);
            new_abstrich = new_abstrich.replace('###STR_RUECK###',rueck);
            new_abstrich = new_abstrich.replace('###DT_RUECKAM###',rueck_am);
            new_abstrich = new_abstrich.replace('###B_BINFORMIERT###',binformiert);
            new_abstrich = new_abstrich.replace('###DT_BINFORMIERT###',binf_am);
            new_abstrich = new_abstrich.replace('###STR_BINFORMIERTDURCH###',binf_durch);
            new_abstrich = new_abstrich.replace('###STR_VERANLASSTDURCH###',durch);
            //leere Felder
            new_abstrich = new_abstrich.replace('###counter###',"");
            new_abstrich = new_abstrich.replace('###counter###',"Vorschau (Fall neu laden für normale Ansicht)");
            new_abstrich = new_abstrich.replace('###DT_VERARBEITETAM###',"");
            new_abstrich = new_abstrich.replace('###B_MOBIL###',"");
            new_abstrich = new_abstrich.replace('###B_DRINGEND###',"");
            new_abstrich = new_abstrich.replace('###B_DIAGNOSTIKZENTRUM###',"");
            
            $('#a_boxes').append(new_abstrich);
        });
        
        $("#new_abstrich_typ").val("");
        $("#new_abstrich_zeitpunkt").val("");
        $("#new_abstrich_rueck").val("");
        $("#new_abstrich_rueckam").val("");
        $("#B_BINFORMIERT0").prop("checked",false);
        $("#DT_BINFORMIERT0").val("");
        $("#STR_BINFORMIERT0").val("");
        $("#new_abstrich_durch").val("");
    }
}

function reload_notes(){
    var note = $("#new_note").val();
    if (note != "") {        
        var new_note;
        $.get('./templates/blocks/note.html', function (response) {
            new_note = response.replace('###TXT_NOTIZ###',note);
            new_note = new_note.replace('###DTINSERT###',$("#new_note_actdate").html());
            new_note = new_note.replace('###STR_DURCH###',$("#new_note_verfasser").html());
            $('#old_notes').prepend(new_note);
        });
        $("#new_note").val("");
    }    
}

function survnet_eintrag(){
    var url = "src/functions/survnet.php";
    
    $("#DT_SURVNETEINGABE").val($.datepicker.formatDate('dd.mm.yy', new Date()));
    
    $("#STR_SURVNETEINGABE").val($("#logged_in_name").html());
    $("#B_SURVNETEINGABE").prop('checked',true);
        
    var data = {LID:$('#LID').val(),B_SURVNETEINGABE:$('#B_SURVNETEINGABE').val(),DT_SURVNETEINGABE:$('#DT_SURVNETEINGABE').val(),STR_SURVNETEINGABE:$('#STR_SURVNETEINGABE').val()};
    
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

function setCheckbox($name){
    if ($("#"+$name).data('preval') == "ja"){
        $("#"+$name).prop('checked', true);
    }  
}

function checkEB(){
    var age = 0;
    var geb = $("#DT_BGEBURTSDATUM").val();
    if (geb && geb != ""){ 
        var d = geb.split(".");
        age = calculate_age(new Date(d[2],d[1],d[0]));
    }
    if (age <= 18){
        $("#show_eb").show();
    } else {
        $("#show_eb").hide();
    }
}

function checkEBMeldeadresse(){
    if ($("#B_BEBANDEREMELDEADRESSE").is(":checked")) { 
        $("#show_ebmeldeadresse").show();
    } else { 
        $("#show_ebmeldeadresse").hide();
    } 
}

function checkAufenthalt(){
    if ($("#B_BANDERERAUFENTHALT").is(":checked")) { 
        $("#show_aufenthalt").show();
    } else { 
        $("#show_aufenthalt").hide();
    } 
}

function checkCritInfra(){
    //alert ('test');
    // => Muss noch Art der CritInfra abfragen!!!
    var sektor = $("#STR_CRITINFRATYP").val();
    
    if ($("#B_CRITINFRA").is(":checked")) {
        $("#critinfra_unv").show();
        $("#critinfra_ausn").show();
    } else {
        $("#critinfra_unv").hide();
        $("#critinfra_ausn").hide();
    }
    
    if ($("#B_CRITINFRA").is(":checked") && sektor == "Sektor Gesundheit"){
        $("#tv_box").show();
        $("#tv_boxes").show();        
    } else {
        $("#tv_box").hide();
        $("#tv_boxes").hide();        
    }
}

function checkQuarantaene(){
    if ($("#B_QUARANTAENE").is(":checked")) {        
        $("#qfreigabe_alert").show();       
    } else { 
        $("#qfreigabe_alert").hide();
    } 
}

function checkTatverbot(){
    if ($("#B_TATVERBOT").is(":checked")) {        
        $("#tfreigabe_alert").show();       
    } else { 
        $("#tfreigabe_alert").hide();
    } 
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

function setBInfdurch(nr){
    if ($("#B_BINFORMIERT"+nr).prop('checked')){
        $("#DT_BINFORMIERT"+nr).val($.datepicker.formatDate('dd.mm.yy', new Date()));
        $("#STR_BINFORMIERT"+nr).val($("#logged_in_name").html());
    } else {
        $("#STR_BINFORMIERT"+nr).val("");
        $("#DT_BINFORMIERT"+nr).val("");
    }
}

function setAbstrZugeschDurch(nr){
    if ($("#B_ZUGESCHICKT"+nr).prop('checked')){
        $("#DT_ZUGESCHICKT"+nr).val($.datepicker.formatDate('dd.mm.yy', new Date()));
        $("#STR_ZUGESCHICKT"+nr).val($("#logged_in_name").html());
    } else {
        $("#STR_ZUGESCHICKT"+nr).val("");
        $("#DT_ZUGESCHICKT"+nr).val("");
    }
}

function askDeleteAbstrich(id){
    var modal = $('#modalDelete');
    modal.find('.modal-title').text('Abstrich löschen');
    modal.find('.modal-body').text('Möchten Sie den Abstrich wirklich löschen?');
    modal.find('#itemToDelete').text(id);
    modal.find('#typeToDelete').text('abstrich');
    modal.modal();
}

function askDeleteQuarantaene(id){
    var modal = $('#modalDelete');
    modal.find('.modal-title').text('Quarantäne löschen');
    modal.find('.modal-body').text('Möchten Sie die Quarantäne wirklich löschen?');
    modal.find('#itemToDelete').text(id);
    modal.find('#typeToDelete').text('quarantaene');
    modal.modal();
}

function askDeleteBluttest(id){
    var modal = $('#modalDelete');
    modal.find('.modal-title').text('Bluttest löschen');
    modal.find('.modal-body').text('Möchten Sie den Bluttest wirklich löschen?');
    modal.find('#itemToDelete').text(id);
    modal.find('#typeToDelete').text('bluttest');
    modal.modal();
}

function askDeleteTatverbot(id){
    var modal = $('#modalDelete');
    modal.find('.modal-title').text('Tätigkeitsverbot löschen');
    modal.find('.modal-body').text('Möchten Sie das Tätigkeitsverbot wirklich löschen?');
    modal.find('#itemToDelete').text(id);
    modal.find('#typeToDelete').text('tatverbot');
    modal.modal();
}

function askDeleteNote(id){
    var modal = $('#modalDelete');
    modal.find('.modal-title').text('Notiz löschen');
    modal.find('.modal-body').text('Möchten Sie die Notiz wirklich löschen?');
    modal.find('#itemToDelete').text(id);
    modal.find('#typeToDelete').text('note');
    modal.modal();
}

function askDeleteFall(id){
    var modal = $('#modalDelete');
    modal.find('.modal-title').text('Fall löschen');
    modal.find('.modal-body').text('Möchten Sie den Fall wirklich löschen?');
    modal.find('#itemToDelete').text(id);
    modal.find('#typeToDelete').text('fall');
    modal.modal();
}

function deleteItem(){
    var id = $('#itemToDelete').text();
    var type = $('#typeToDelete').text();
    //alert(id);
    var url = "src/data/delete.php";
    $.ajax({
        type: "POST",
        url: url,
        data: {type: type,id: id}, 
        success: function(deleted)
        {
           if (deleted){
               var modal = $('#modalInfo');
               modal.find('.modal-body').text('Der Eintrag wurde gelöscht');
               modal.modal();               
           } else {
               var modal = $('#modalError');
               modal.find('.modal-body').text('Fehler beim Löschen des Eintrags!');
               modal.modal();
           }
            if (type == 'fall'){
                window.location.href = "?type=all";
            } else {
                $('#'+type+id+'_box').hide();
            }
            
        }        
    });
    
    var modal = $('#modalDelete');
    modal.find('#itemToDelete').text(-1);    
    modal.find('#typeToDelete').text("");    
    modal.modal('toggle');
}

function askCloseFall(id){
    var modal = $('#modalClose');
    modal.find('.modal-title').text('Fall schliessen');
    modal.find('.modal-body').text('Möchten Sie den Fall wirklich abschließen?');
    modal.find('#itemTo').text(id);
    modal.find('#typeTo').text('close');
    modal.modal();
}