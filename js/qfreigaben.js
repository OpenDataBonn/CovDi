$(document).ready(function(){
    $("#freigabe_form").submit(function(e) {
        e.preventDefault(); // avoid to execute the actual submit of the form.
        
        var form = $(this);
        var url = "src/freigabe/qfreigabe.php";

        $.ajax({
            type: "POST",
            url: url,
            data: form.serialize(), // serializes the form's elements.
            success: function(saved)
            {
               location.reload();               
            }
        });
    });

});