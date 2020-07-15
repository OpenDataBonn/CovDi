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

