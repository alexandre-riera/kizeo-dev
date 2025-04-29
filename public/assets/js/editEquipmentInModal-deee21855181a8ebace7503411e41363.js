function editEquipement(idEquipementAndIdRow,libelleEquipement,visite,raisonSociale,modeleNacelle,hauteurNacelle,ifExistDB,signatureTech,trigrammeTech,anomalies,idContact,idSociete,codeAgence,numeroEquipement,modeFonctionnement,repereSiteClient,miseEnService,numeroDeSerie,marque,hauteur,largeur,longueur,plaqueSignaletique,etat,derniereVisite,statutDeMaintenance,presenceCarnetEntretien,statutConformite){
    // Try get id_contact on equipment to prepare request save in DB
    // Populate MODAL fields from selected table row
    document.getElementById("modal_id").value = idEquipementAndIdRow;
    document.getElementById("modal_libelle").value = libelleEquipement;
    document.getElementById("modal_visite").value = visite;
    document.getElementById("modal_raisonSociale").value = raisonSociale;
    document.getElementById("modal_modeleNacelle").value = modeleNacelle;
    document.getElementById("modal_hauteurNacelle").value = hauteurNacelle;
    document.getElementById("modal_ifExistDB").value = ifExistDB;
    document.getElementById("modal_signatureTech").value = signatureTech;
    document.getElementById("modal_trigrammeTech").value = trigrammeTech;
    document.getElementById("modal_anomalies").value = anomalies;
    document.getElementById("modal_idContact").value = idContact;
    document.getElementById("modal_idSociete").value = idSociete;
    document.getElementById("modal_codeAgence").value = codeAgence;
    document.getElementById("modal_trigramme").value = numeroEquipement;
    document.getElementById("modal_modeFonctionnement").value = modeFonctionnement;
    document.getElementById("modal_repereSiteClient").value = repereSiteClient;
    document.getElementById("modal_miseEnService").value = miseEnService;
    document.getElementById("modal_numeroDeSerie").value = numeroDeSerie;
    document.getElementById("modal_marque").value = marque;
    document.getElementById("modal_hauteur").value = hauteur;
    document.getElementById("modal_largeur").value = largeur;
    document.getElementById("modal_longueur").value = longueur;
    document.getElementById("modal_plaqueSignaletique").value = plaqueSignaletique;
    document.getElementById("modal_etat").value = etat;
    var prochainStatut = document.getElementById("modal_prochain_statut").value;
    var nom = document.getElementById("modal_nom").value;
    var prenom = document.getElementById("modal_prenom").value;
    document.getElementById("modal_derniereVisite").value = derniereVisite;
    document.getElementById("modal_statut").value = statutDeMaintenance;
    document.getElementById("modal_carnetEntretien").value = presenceCarnetEntretien;
    document.getElementById("modal_statutConformite").value = statutConformite;

    console.log("id equipement et id row: " + idEquipementAndIdRow);
    // console.log("libelleEquipement: " +libelleEquipement);
    // console.log("visite: " +visite);
    // console.log("raisonSociale: " +raisonSociale);
    // console.log("modeleNacelle: " +modeleNacelle);
    // console.log("hauteurNacelle: " +hauteurNacelle);
    // console.log("ifExistDB: " +ifExistDB);
    // console.log("signatureTech: " +signatureTech);
    // console.log("trigrammeTech: " +trigrammeTech);
    // console.log("anomalies: " +anomalies);
    // console.log("idContact: " +idContact);
    // console.log("idSociete: " +idSociete);
    // console.log("codeAgence: " +codeAgence);
    // console.log("numeroEquipement: " +numeroEquipement);
    // console.log("modeFonctionnement: " +modeFonctionnement);
    // console.log("repereSiteClient: " +repereSiteClient);
    // console.log("miseEnService: " +miseEnService);
    // console.log("numeroDeSerie: " +numeroDeSerie);
    // console.log("marque: " +marque);
    // console.log("hauteur: " +hauteur);
    // console.log("largeur: " +largeur);
    // console.log("longueur: " +longueur);
    // console.log("plaqueSignaletique: " +plaqueSignaletique);
    // console.log("etat: " +etat);
    // console.log("derniereVisite: " +derniereVisite);
    // console.log("statutDeMaintenance: " +statutDeMaintenance);
    // console.log("presenceCarnetEntretien: " +presenceCarnetEntretien);
    // console.log("statutConformite: " +statutConformite);

    // On save Modal
    // document.getElementById('saveEquipmentFromModal').addEventListener('click', function(){
    //     // get the table row ID that was passed to the modal
    //     var row_id = idEquipementAndIdRow;

    //     // get the table row to update
    //     //row
    //     var row = document.getElementById(row_id);

    //     // update the table row's cells with data from the modal fields
    //     //c = row.children();
    //     var c = row.childNodes;
    //     console.log(c);
    //     c[0].textContent = document.getElementById("modal_visite").value;  // visite
    //     c[1].textContent = document.getElementById("modal_trigramme").value; // Trigramme interne
    //     c[2].textContent = document.getElementById("modal_trigramme").value; // Libelle
    //     c[3].textContent = document.getElementById("modal_trigramme").value; // Dernière visite
    //     c[4].textContent = document.getElementById("modal_trigramme").value; // État
    //     c[5].textContent = document.getElementById("modal_trigramme").value; // Statut de maintenance
    // })
}