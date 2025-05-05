<?php

namespace App\Controller;

// ENTITY CONTACT
use App\Entity\ContactS10;
use App\Entity\ContactS40;
use App\Entity\ContactS50;
use App\Entity\ContactS60;
use App\Entity\ContactS70;
use App\Entity\ContactS80;
use App\Entity\ContratS10;
use App\Entity\ContratS40;
use App\Entity\ContratS50;
use App\Entity\ContratS60;
use App\Entity\ContratS70;
use App\Entity\ContratS80;
// ENTITY CONTRAT
use App\Entity\ContactS100;
use App\Entity\ContactS120;
use App\Entity\ContactS130;
use App\Entity\ContactS140;
use App\Entity\ContactS150;
use App\Entity\ContactS160;
use App\Entity\ContactS170;
use App\Entity\ContratS100;
use App\Entity\ContratS120;
use App\Entity\ContratS130;
use App\Entity\ContratS140;
use App\Entity\ContratS150;
use App\Entity\ContratS160;

use App\Entity\ContratS170;
use App\Entity\EquipementS10;
use App\Entity\EquipementS40;
use App\Entity\EquipementS50;
use App\Entity\EquipementS60;
use App\Entity\EquipementS70;
use App\Entity\EquipementS80;
use App\Service\KizeoService;
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use App\Repository\ContratRepositoryS10;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ContratController extends AbstractController
{
    private $kizeoService;

    public function __construct(KizeoService $kizeoService)
    {
        $this->kizeoService = $kizeoService;
    }

    #[Route('/contrat/new', name: 'app_contrat_new', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager, ContratRepositoryS10 $contratRepositoryS10): Response
    {
        $agences = [
            'S10' => 'Group',
            'S40' => 'St Etienne',
            'S50' => 'Grenoble',
            'S60' => 'Lyon',
            'S70' => 'Bordeaux',
            'S80' => 'Paris Nord',
            'S100' => 'Montpellier',
            'S120' => 'Hauts de France',
            'S130' => 'Toulouse',
            'S140' => 'Epinal',
            'S150' => 'PACA',
            'S160' => 'Rouen',
            'S170' => 'Rennes'
        ];

        $contact = [];
        $contactsKizeo = [];
        $contactsFromKizeo = [];
        $contactsFromKizeoSplittedInObject = [];
        
        $contactName = "";
        $contactId = "";
        $contactAgence = "";
        $contactCodePostal = "";
        $contactVille = "";
        $contactIdSociete = "";
        $contactEquipSupp1 = "";
        $contactEquipSupp2 = "";

        $typesEquipements = $contratRepositoryS10->getTypesEquipements();
        $modesFonctionnement = $contratRepositoryS10->getModesFonctionnement();
        $typesValorisation = $contratRepositoryS10->getTypesValorisation();
        $visites = $contratRepositoryS10->getVisites();

        // ID de la liste contact à passer à la fonction updateListContactOnKizeo($idListContact)
        $idListContact = "";

        // HANDLE AGENCY SELECTION
        $agenceSelectionnee = "";
        if(isset($_POST['submit_agence'])){  
            if(!empty($_POST['agence'])) {  
                $agenceSelectionnee = $_POST['agence'];
            } 
        }

        // HANDLE CONTACT SELECTION
        $contactSelectionne = "";
        if(isset($_POST['submit_contact'])){
            if(!empty($_POST['clientName'])) {  
                $contactSelectionne = $_POST['clientName'];
            }
            if ($contactSelectionne != "") {
                // Explode contact string : raison_sociale|id_contact|agence
                $contactArrayCutted = explode("|", $contactSelectionne);
                $contactName = $contactArrayCutted[0];
                $contactId = $contactArrayCutted[1];
                $contactAgence = $contactArrayCutted[2];
                $contactCodePostal = $contactArrayCutted[3];
                $contactVille = $contactArrayCutted[4];
                if (isset($contactArrayCutted[5])) {
                    $contactIdSociete = $contactArrayCutted[5];
                }
                if (isset($contactArrayCutted[6])) {
                    $contactEquipSupp1 = $contactArrayCutted[6];
                }
                if (isset($contactArrayCutted[7])) {
                    $contactEquipSupp2 = $contactArrayCutted[7];
                }
            }
        }

        if ($agenceSelectionnee != "") {
            $contactsKizeo = $this->kizeoService->getContacts($agenceSelectionnee);
        }
        foreach ($contactsKizeo as $kizContact) {
            array_push($contactsFromKizeo, $kizContact);
        }

        foreach ($contactsFromKizeo as $contact) {
            $contactSplittedInObject = $this->kizeoService->StringToContactObject($contact);
            array_push($contactsFromKizeoSplittedInObject, $contactSplittedInObject);
        }        

        /**
        * 
        *Explication :
        *
        *array_filter() : Cette fonction parcourt le tableau $contactsFromKizeoSplittedInObject et applique une fonction de rappel (callback) à chaque élément.
        *Fonction de rappel (callback) :
        *Elle prend un objet $contact du tableau comme argument.
        *Elle utilise isset() pour vérifier si l'objet $contact a les clés 'id_societe', 'equipement_supp_1' ou 'equipement_supp_2'.
        *Elle retourne true si au moins une de ces clés existe, et false sinon.
        *Résultat : array_filter() retourne un nouveau tableau contenant uniquement les objets pour lesquels la fonction de rappel a retourné true
        */
        $contactsFromKizeoSplittedInObject = array_filter(
            $contactsFromKizeoSplittedInObject,
            function ($contact) {
                return isset($contact->id_societe) && isset($contact->equipement_supp_1) && isset($contact->equipement_supp_2);
            }
        );
        $clientSelectedInformations = "";

        // PUT THE LOGIC IN THE "SWITCH" IF CONTACTAGENCE EQUAL S50, SEARCH CONTRACT IN ENTITY CONTRATS50 WITH HIS CONTACTID
        $theAssociatedContract = "";

        $formContrat = "";
        // GET CLIENT SELECTED INFORMATIONS ACCORDING TO HIS CONTACTID
        if ($contactId != "") {
            switch ($contactAgence) {
                case 'S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $contactId]);
                    break;
                case ' S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $contactId]);                   
                    break;
                case ' S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $contactId]);
                    break;
                case ' S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $contactId]);
                    break;
                case 'S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case 'S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                case ' S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $contactId]);                    
                    break;
                
                default:
                    break;
            }

            // GET Contrat informations ---- PUT THIS CALL IN EVERY CASES ADDING HIS PROPER CONTACTAGENCE
            $theAssociatedContract = $contratRepositoryS10->findContratByIdContact($contactId);
        }

        if(isset($_POST['numero_contrat'])){
            $contactAgence = $_POST['contact_agence'];
            $contactRaisonSociale = $_POST['contact_raison_sociale'];

            switch ($contactAgence) {
                case 'S10':
                    $this->newContract(ContratS10::class, EquipementS10::class, $entityManager, $contactAgence, $contactRaisonSociale);
                    break;
                case 'S40':
                    $this->newContract(ContratS40::class, EquipementS40::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S50':
                    $this->newContract(ContratS50::class, EquipementS50::class, $entityManager, $contactAgence, $contactRaisonSociale);
                    break;
                case 'S60':
                    $this->newContract(ContratS60::class, EquipementS60::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S70':
                    $this->newContract(ContratS70::class, EquipementS70::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S80':
                    $this->newContract(ContratS80::class, EquipementS80::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S100':
                    $this->newContract(ContratS100::class, EquipementS100::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S120':
                    $this->newContract(ContratS120::class, EquipementS120::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S130':
                    $this->newContract(ContratS130::class, EquipementS130::class, $entityManager, $contactAgence, $contactRaisonSociale);                   
                    break;
                case 'S140':
                    $this->newContract(ContratS140::class, EquipementS140::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S150':
                    $this->newContract(ContratS150::class, EquipementS150::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S160':
                    $this->newContract(ContratS160::class, EquipementS160::class, $entityManager, $contactAgence, $contactRaisonSociale);                    
                    break;
                case 'S170':
                    $this->newContract(ContratS170::class, EquipementS170::class, $entityManager, $contactAgence, $contactRaisonSociale);                   
                    break;
                
                default:
                    break;
            }
        }
        
        return $this->render('contrat/index.html.twig', [
            'agences' => $agences,
            'contact' => $contact,
            'agenceSelectionnee' => $agenceSelectionnee,
            'contactSelectionne' => $contactSelectionne,
            'contactsFromKizeo' => $contactsFromKizeo,
            'contactsFromKizeoSplittedInObject' => $contactsFromKizeoSplittedInObject,
            'contactName' => $contactName,
            'contactId' => $contactId,
            'contactAgence' => $contactAgence,
            'contactCodePostal' => $contactCodePostal,
            'contactVille' => $contactVille,
            'contactIdSociete' => $contactIdSociete,
            'contactEquipSupp1' => $contactEquipSupp1,
            'contactEquipSupp2' => $contactEquipSupp2,
            'clientSelectedInformations' => $clientSelectedInformations,
            'theAssociatedContract' => $theAssociatedContract,
            'typesEquipements' => $typesEquipements,
            'modesFonctionnement' => $modesFonctionnement,
            'visites' => $visites,
            'formContrat' => $formContrat,
            'typesValorisation' => $typesValorisation,
        ]);
    }

    public function newContract($entityContrat, $entityEquipement, $entityManager, $contactAgence, $contactRaisonSociale)
    {
        $contrat = new $entityContrat;
        $contact = null;
        switch($contactAgence){
            case 'S10':
                $contact = $entityManager->getRepository(ContactS10::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S40':
                $contact = $entityManager->getRepository(ContactS40::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S50':
                $contact = $entityManager->getRepository(ContactS50::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S60':
                $contact = $entityManager->getRepository(ContactS60::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S70':
                $contact = $entityManager->getRepository(ContactS70::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S80':
                $contact = $entityManager->getRepository(ContactS80::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S100':
                $contact = $entityManager->getRepository(ContactS100::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S120':
                $contact = $entityManager->getRepository(ContactS120::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S130':
                $contact = $entityManager->getRepository(ContactS130::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S140':
                $contact = $entityManager->getRepository(ContactS140::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S150':
                $contact = $entityManager->getRepository(ContactS150::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S160':
                $contact = $entityManager->getRepository(ContactS160::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            case 'S170':
                $contact = $entityManager->getRepository(ContactS170::class)->findBy(array('id_contact' => $_POST['contact_id']))[0];
            break;
            default:
            break;
        }

        $contrat->setNumeroContrat($_POST['numero_contrat']);
        $contrat->setContact($contact);
        $contrat->setIdContact($_POST['contact_id']);
        $contrat->setDateSignature($_POST['date_signature']);
        $contrat->setValorisation($_POST['type_valorisation'][0]);
        $contrat->setNombreEquipement($_POST['nombre_equipements_total']);
        $contrat->setNombreVisite($_POST['nombre_visite']);
        $contrat->setDatePrevisionnelle1($_POST['date_previsionnelle']);
        //Gestion de tacite reconduction
        if(isset($_POST['tacite_reconduction_oui'])){
            $contrat->setTaciteReconduction($_POST['tacite_reconduction_oui']);
        }
        $contrat->setdateResiliation("Contrat en cours");
        //gestion de la durée.
        if(!empty($_POST['duree'])){
        $contrat->setDuree($_POST['duree']);
        }
        if($contrat->getDateResiliation() !== null || $contrat->getDateResiliation() !== ""){
            $contrat->setStatut('Contrat en cours');
        }else{
            $contrat->setStatut('Contrat résilié');
        }
        $entityManager->persist($contrat);
        $entityManager->flush();


        // Get new created Contract to save his equipments
        $contratForEquipementSave = null;
        switch($contactAgence){
            case 'S10':
                $contratForEquipementSave = $entityManager->getRepository(ContratS10::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S40':
                $contratForEquipementSave = $entityManager->getRepository(ContratS40::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S50':
                $contratForEquipementSave = $entityManager->getRepository(ContratS50::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S60':
                $contratForEquipementSave = $entityManager->getRepository(ContratS60::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S70':
                $contratForEquipementSave = $entityManager->getRepository(ContratS70::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S80':
                $contratForEquipementSave = $entityManager->getRepository(ContratS80::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S100':
                $contratForEquipementSave = $entityManager->getRepository(ContratS100::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S120':
                $contratForEquipementSave = $entityManager->getRepository(ContratS120::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S130':
                $contratForEquipementSave = $entityManager->getRepository(ContratS130::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S140':
                $contratForEquipementSave = $entityManager->getRepository(ContratS140::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S150':
                $contratForEquipementSave = $entityManager->getRepository(ContratS150::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S160':
                $contratForEquipementSave = $entityManager->getRepository(ContratS160::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            case 'S170':
                $contratForEquipementSave = $entityManager->getRepository(ContratS170::class)->findBy(array('numero_contrat' => $_POST['numero_contrat']))[0];
            break;
            default:
            break;
        }

        // -----------------------------------------      REPRENDRE ICI POUR LE TRAITEMENT DES EQUIPEMENTS AVEC LA FONCTION saveContractEquipments ---------------------------------------
        // -----------------------------------------      Traitement des lignes équipement du contrat ---------------------------------------
        //  foreach ($_POST['type_equipement'] as $key => $ligneEquipement) {
        //      $this->saveContractEquipments($entityEquipement,$entityManager,$contratForEquipementSave,$key, $contactRaisonSociale, $ligneEquipement);
        // }
    }

    /**
     * @return equipment new line template used by fetch API for + button
     */
    #[Route('/equipements/new-line', name: 'app_equipement_new_line', methods: ['GET'])]
    public function newLine(): Response
    {
        
        $typesEquipements = [
            "Barrière levante",
            "Bloc roue",
            "Mini-pont",
            "Niveleur",
            "Plaque de quai",
            "Portail",
            "Porte accordéon",
            "Porte coulissante",
            "Porte coupe-feu",
            "Porte frigorifique",
            "Porte piétonne",
            "Porte rapide",
            "Porte sectionnelle",
            "Protection",
            "Rideau métallique",
            "SAS",
            "Table élévatrice",
            "Tourniquet",
            "Volet roulant",
        ];
        $modesFonctionnement = [
            "Manuel",
            "Motorisé",
            "Mixte",
            "Impulsion",
            "Automatique",
            "Hydraulique"
        ];
        $visites = [
            "Nécessite 1 visite par an",
            "Nécessite 2 visites par an",
            "Nécessite 3 visites par an",
            "Nécessite 4 visites par an",
        ];

        return $this->render('equipement/_new_line.html.twig', [
            'typesEquipements' => $typesEquipements,
            'modesFonctionnement' => $modesFonctionnement,
            'visites' => $visites,
        ]);
    }
    /**
     * @return visites available for equipments and contract form
     */
    #[Route('/get_visites/{nombreVisites}', name: 'get_visites', methods: ['GET'])]
    public function getVisites(int $nombreVisites): JsonResponse
    {
        $visites = [];
        switch ($nombreVisites) {
            case '1':
                $visites[] = 'Nécessite 1 visite par an';
                break;
            case '2':
                array_push($visites ,'Nécessite 1 visite par an', 'Nécessite 2 visites par an');
                break;
            case '3':
                array_push($visites ,'Nécessite 1 visite par an', 'Nécessite 2 visites par an', 'Nécessite 3 visites par an');
                break;
            case '4':
                array_push($visites ,'Nécessite 1 visite par an', 'Nécessite 2 visites par an', 'Nécessite 3 visites par an', 'Nécessite 4 visites par an');
                break;
            
            default:
                break;
        }

        return new JsonResponse($visites);
    }

    /**
     * Traitement des lignes équipement du contrat 
     * Save agency contract equipments by type and visit
     */
    public function saveContractEquipments($entityEquipement, $entityManager, $contratForEquipementSave, $key, $contactRaisonSociale, $ligneEquipement)
    {
        switch($entityEquipement){
            case EquipementS10::class :
                switch ($_POST['visite_equipement'][$key]) {
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][$key]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][$key]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipement);
                            $entityManager->flush();
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][$key]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][$key]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                            $entityManager->flush();
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS10($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS40::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS40($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS50::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS50($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS60::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS60($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS70::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS70($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS80::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS80($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS100::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS100($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS120::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS120($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS130::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS130($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS140::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS140($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS150::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS150($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS160::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS160($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
            case EquipementS170::class :
                switch ($_POST['visite_equipement'][0]) { // Accès à l'élément [0]
                    case 'Nécessite 1 visite par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipement = new $entityEquipement;
                            $equipement->setIdContact($_POST['contact_id']);
                            $equipement->setRaisonSociale($contactRaisonSociale);
                            $equipement->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipement->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipement->setVisite($_POST['nombre_visite'] == 1 ? 'CEA' : 'CE1');
                            $equipement->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipement);
                        }
                        break;
                    case 'Nécessite 2 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
                        }
                        break;
                    case 'Nécessite 3 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
                        }
                        break;
                    case 'Nécessite 4 visites par an':
                        for ($i = 0; $i < $ligneEquipement; $i++) {
                            $equipementCE1 = new $entityEquipement;
                            $equipementCE1->setIdContact($_POST['contact_id']);
                            $equipementCE1->setRaisonSociale($contactRaisonSociale);
                            $equipementCE1->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE1->setVisite('CE1');
                            $equipementCE1->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE1);
    
                            $equipementCE2 = new $entityEquipement;
                            $equipementCE2->setIdContact($_POST['contact_id']);
                            $equipementCE2->setRaisonSociale($contactRaisonSociale);
                            $equipementCE2->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE2->setVisite('CE2');
                            $equipementCE2->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE2);
    
                            $equipementCE3 = new $entityEquipement;
                            $equipementCE3->setIdContact($_POST['contact_id']);
                            $equipementCE3->setRaisonSociale($contactRaisonSociale);
                            $equipementCE3->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE3->setVisite('CE3');
                            $equipementCE3->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE3);
    
                            $equipementCE4 = new $entityEquipement;
                            $equipementCE4->setIdContact($_POST['contact_id']);
                            $equipementCE4->setRaisonSociale($contactRaisonSociale);
                            $equipementCE4->setLibelleEquipement($_POST['type_equipement'][0]);
                            $equipementCE4->setModeFonctionnement($_POST['mode_fonctionnement'][0]);
                            $equipementCE4->setVisite('CE4');
                            $equipementCE4->setContratS170($contratForEquipementSave);
                            $entityManager->persist($equipementCE4);
                        }
                        break;
                    default:
                        break;
                }
            break;
        }
    }
}
