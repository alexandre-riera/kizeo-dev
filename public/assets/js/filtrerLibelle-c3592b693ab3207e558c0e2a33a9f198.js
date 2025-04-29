function filtrerLibelle()
{
  var select = document.getElementById('libelle-dropdown').selectedIndex;
  // select+=1;
  var data = "";

  var row = document.getElementsByTagName('tr');
  for (var i = 0; i < row.length; i++)
  {
    switch (select) {
      case 1:
        data = "porte sectionnelle";
        break;
      case 2:
        data = "porte rapide";
        break;
      case 3:
        data = "rideau metallique";
        break;
      case 4:
        data = "portail";
        break;
      case 5:
        data = "barriere levante";
        break;
      case 6:
        data = "bloc roue";
        break;
      case 7:
        data = "niveleur";
        break;
      case 8:
        data = "protection";
        break;
      case 9:
        data = "table elevatrice";
        break;
    
      default:
        break;
    }
    console.log("Le filtre de statut de maintenance est  " + data + " avec un id egal à " + select);
    if(row[i].dataset.libelle != data) row[i].style.display = 'none';
    if(row[i].dataset.libelle == data) row[i].style.display = 'table-row'; // j'avais oublié cette ligne
  }
}

function resetLibelle(){
  var select = document.getElementById('libelle-dropdown').selectedIndex;
  select+=7;
  var data = document.getElementsByTagName('option')[select].value;
  var row = document.getElementsByTagName('tr');
  for (var i = 0; i < row.length; i++)
  {
    row[i].style.display = 'table-row'; // Remettre toute les lignes en visible
  }
}