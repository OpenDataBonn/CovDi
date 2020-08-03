$(document).ready(function(){
    var activeTab = localStorage.getItem('activeTab');
    if(activeTab){
        localStorage.removeItem('activeTab');
    }
});