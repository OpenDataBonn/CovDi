$(document).ready(function(){
    $('#base_report_button').click(function() {
        html2canvas(document.querySelector("#base_report_img")).then(canvas => {
            //document.body.appendChild(canvas)
            saveAs(canvas.toDataURL(), 'CovDiBasisZahlen.png');
        });
    });   
});

function saveAs(uri, filename) {
    var link = document.createElement('a');
    if (typeof link.download === 'string') {
        link.href = uri;
        link.download = filename;
        //Firefox requires the link to be in the body
        document.body.appendChild(link);
        //simulate click
        link.click();
        //remove the link when done
        document.body.removeChild(link);
    } else {
        window.open(uri);
    }
}