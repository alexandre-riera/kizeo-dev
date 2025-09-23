function expand(obj)
{
    obj.size = 5;
}
function unexpand(obj)
{
    obj.size = 1;
}
  
function filterFunction() {
    const input = document.getElementById("searchClients");
    const filter = input.value.toUpperCase();
    console.log("Filter text in input : " + filter);
    const select = document.getElementById("clientsList");
    const option = select.getElementsByTagName("option");

    for (let i = 0; i < option.length; i++) {
        txtValue = option[i].textContent || option[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            // select.focus();
            expand(select);
        option[i].style.display = "";
        } else {
        option[i].style.display = "none";
        }
    }
}