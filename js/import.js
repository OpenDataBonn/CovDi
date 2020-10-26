$(document).ready(function(){
    $("#importMs_form").submit(function(e) {
        e.preventDefault(); // avoid to execute the actual submit of the form.
        
        var formData = new FormData(this);
        var url = "src/data/import.php";

        $.ajax({
            type: "POST",
            url: url,
            data: formData,
            success: function(sims)
            {
               $("#importMInfo").html(sims);           
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });
    
    $("#importMInfo").on("submit","form",function(e) {
        e.preventDefault(); // avoid to execute the actual submit of the form.
        
        var formData = new FormData(this);
        var url = "src/data/import.php";

        $.ajax({
            type: "POST",
            url: url,
            data: formData,
            success: function(sims)
            {
               $("#importMInfo").html(sims);           
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });
});