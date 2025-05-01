function filtrerStatutDeMaintenance()
{
  var select = document.getElementById('statutdemaintenance-dropdown').selectedIndex;
  select+=23;
  var data = document.getElementsByTagName('option')[select].value;

  console.log("Le filtre de statut de maintenance est  " + data + " avec un id egal à " + select);
  
  var row = document.getElementsByTagName('tr');
  console.log("row.length : " + row.length);
  for (var i = 0; i < row.length; i++){
    // var tempName = row[i].dataset.statutdemaintenance;
    // console.log(tempName);
    if(row[i].dataset.statut != data) row[i].style.display = 'none';
    if(row[i].dataset.statut == data) row[i].style.display = 'table-row'; // j'avais oublié cette ligne
  }
}

function resetStatutDeMaintenance(){
  var select = document.getElementById('statutdemaintenance-dropdown').selectedIndex;
  select+=15;
  var data = document.getElementsByTagName('option')[select].value;
  var row = document.getElementsByTagName('tr');
  for (var i = 0; i < row.length; i++)
  {
    row[i].style.display = 'table-row'; // Remettre toute les lignes en visible
  }
}