function handleEquipementsTableFilters(){
    var selectVisite = document.getElementById('visit-dropdown').selectedIndex;
    var selectLibelle = document.getElementById('libelle-dropdown').selectedIndex;
    var selectStatutdemaintenance = document.getElementById('statutdemaintenance-dropdown').selectedIndex;
    selectStatutdemaintenance += 23;

    console.log(selectVisite, selectLibelle, selectStatutdemaintenance);
    var visitData = "";
    var libelleData = "";
    var statutDeMaintenanceData = "";
    // Écrire : Si selectVisite égal à tant, visitData = "", Si selectLibelle égal à tant, libelleData = "", Si selectVisite égal à tant, selectStatutdemaintenance = ""
    visitData = document.getElementsByTagName('option')[selectVisite].value;
    libelleData = document.getElementsByTagName('option')[selectLibelle].value;
    statutDeMaintenanceData = document.getElementsByTagName('option')[selectStatutdemaintenance].value;
    
    console.log(visitData, libelleData, statutDeMaintenanceData);
    var data = "";

    var row = document.getElementsByTagName('tr');
    for (var i = 0; i < row.length; i++){
        if(row[i].dataset.visite != visitData || row[i].dataset.libelle != libelleData || row[i].dataset.statut != statutDeMaintenanceData) row[i].style.display = 'none';
        if(row[i].dataset.visite == visitData || row[i].dataset.libelle == libelleData || row[i].dataset.statut == statutDeMaintenanceData) row[i].style.display = 'table-row'; // j'avais oublié cette ligne
    }
}