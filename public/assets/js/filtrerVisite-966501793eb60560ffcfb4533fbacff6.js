function filtrerVisite()
{
  var select = document.getElementById('visit-dropdown').selectedIndex;
 
  var data = "";

  switch (select) {
    case 0:
        data="Tous";
      break;
    case 1:
        data="CE1";
      break;
    case 2:
        data="CE2";
      break;
    case 3:
        data="CE3";
      break;
    case 4:
        data="CE4";
      break;
    case 5:
        data="CEA";
      break;
  
    default:
      data="Tous"
      break;
  }

  console.log("La visite sélectionnée est " + data + " avec un id egal à " + select);
  
  var row = document.getElementsByTagName('tr');
  for (var i = 0; i < row.length; i++)
    {
    if(row[i].dataset.visite != data) row[i].style.display = 'none';
    if(row[i].dataset.visite == data) row[i].style.display = 'table-row'; // j'avais oublié cette ligne
  }
}

function resetVisite(){
  var select = document.getElementById('visit-dropdown').selectedIndex;
  var row = document.getElementsByTagName('tr');
  for (var i = 0; i < row.length; i++)
  {
    row[i].style.display = 'table-row'; // Remettre toute les lignes en visible
  }
}