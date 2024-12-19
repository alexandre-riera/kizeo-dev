function getImagesFromIdEquipement(idEquipementAndIdRow,libelleEquipement,visite,raisonSociale,modeleNacelle,hauteurNacelle,ifExistDB,signatureTech,trigrammeTech,anomalies,idContact,idSociete,codeAgence,numeroEquipement,modeFonctionnement,repereSiteClient,miseEnService,numeroDeSerie,marque,hauteur,largeur,longueur,plaqueSignaletique,etat,derniereVisite,statutDeMaintenance,presenceCarnetEntretien,statutConformite){
    // Try get id_contact on equipment to prepare request save in DB
    // Populate MODAL fields from selected table row
    
    document.getElementById("modal_show_id").value = idEquipementAndIdRow;
    document.getElementById("modal_show_libelle").value = libelleEquipement;
    document.getElementById("modal_show_visite").value = visite;
    document.getElementById("modal_show_raisonSociale").value = raisonSociale;
    document.getElementById("modal_show_modeleNacelle").value = modeleNacelle;
    document.getElementById("modal_show_hauteurNacelle").value = hauteurNacelle;
    document.getElementById("modal_show_ifExistDB").value = ifExistDB;
    document.getElementById("modal_show_signatureTech").value = signatureTech;
    document.getElementById("modal_show_trigrammeTech").value = trigrammeTech;
    document.getElementById("modal_show_anomalies").value = anomalies;
    document.getElementById("modal_show_idContact").value = idContact;
    document.getElementById("modal_show_idSociete").value = idSociete;
    document.getElementById("modal_show_codeAgence").value = codeAgence;
    document.getElementById("modal_show_trigramme").value = numeroEquipement;
    document.getElementById("modal_show_modeFonctionnement").value = modeFonctionnement;
    document.getElementById("modal_show_repereSiteClient").value = repereSiteClient;
    document.getElementById("modal_show_miseEnService").value = miseEnService;
    document.getElementById("modal_show_numeroDeSerie").value = numeroDeSerie;
    document.getElementById("modal_show_marque").value = marque;
    document.getElementById("modal_show_hauteur").value = hauteur;
    document.getElementById("modal_show_largeur").value = largeur;
    document.getElementById("modal_show_longueur").value = longueur;
    document.getElementById("modal_show_plaqueSignaletique").value = plaqueSignaletique;
    document.getElementById("modal_show_etat").value = etat;
    document.getElementById("modal_show_derniereVisite").value = derniereVisite;
    document.getElementById("modal_show_statut").value = statutDeMaintenance;
    document.getElementById("modal_show_carnetEntretien").value = presenceCarnetEntretien;
    document.getElementById("modal_show_statutConformite").value = statutConformite;

    console.log("IdEquipement: " +idEquipementAndIdRow);
    console.log("libelleEquipement: " +libelleEquipement);
    console.log("visite: " +visite);
    console.log("raisonSociale: " +raisonSociale);
    console.log("modeleNacelle: " +modeleNacelle);
    console.log("hauteurNacelle: " +hauteurNacelle);
    console.log("ifExistDB: " +ifExistDB);
    console.log("signatureTech: " +signatureTech);
    console.log("trigrammeTech: " +trigrammeTech);
    console.log("anomalies: " +anomalies);
    console.log("idContact: " +idContact);
    console.log("idSociete: " +idSociete);
    console.log("codeAgence: " +codeAgence);
    console.log("numeroEquipement: " +numeroEquipement);
    console.log("modeFonctionnement: " +modeFonctionnement);
    console.log("repereSiteClient: " +repereSiteClient);
    console.log("miseEnService: " +miseEnService);
    console.log("numeroDeSerie: " +numeroDeSerie);
    console.log("marque: " +marque);
    console.log("hauteur: " +hauteur);
    console.log("largeur: " +largeur);
    console.log("longueur: " +longueur);
    console.log("plaqueSignaletique: " +plaqueSignaletique);
    console.log("etat: " +etat);
    console.log("derniereVisite: " +derniereVisite);
    console.log("statutDeMaintenance: " +statutDeMaintenance);
    console.log("presenceCarnetEntretien: " +presenceCarnetEntretien);
    console.log("statutConformite: " +statutConformite);

}