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
use App\Entity\ContactS100;
// ENTITY CONTRAT
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

        // dump($typesEquipements);
        // dump($modesFonctionnement);
        // dump($visites);
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
        dump($contactId);
        dump($contactAgence);
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
            var_dump($_POST);
            switch ($contactAgence) {
                case 'S10':
                    $this->newContract(ContratS10::class, EquipementS10::class, $entityManager);
                    break;
                case 'S40':
                    $this->newContract(ContratS40::class, EquipementS40::class, $entityManager);                    
                    break;
                case 'S50':
                    $this->newContract(ContratS50::class, EquipementS50::class, $entityManager);
                    break;
                case 'S60':
                    $this->newContract(ContratS60::class, EquipementS60::class, $entityManager);                    
                    break;
                case 'S70':
                    $this->newContract(ContratS70::class, EquipementS70::class, $entityManager);                    
                    break;
                case 'S80':
                    $this->newContract(ContratS80::class, EquipementS80::class, $entityManager);                    
                    break;
                case 'S100':
                    $this->newContract(ContratS100::class, EquipementS100::class, $entityManager);                    
                    break;
                case 'S120':
                    $this->newContract(ContratS120::class, EquipementS120::class, $entityManager);                    
                    break;
                case 'S130':
                    $this->newContract(ContratS130::class, EquipementS130::class, $entityManager);                   
                    break;
                case 'S140':
                    $this->newContract(ContratS140::class, EquipementS140::class, $entityManager);                    
                    break;
                case 'S150':
                    $this->newContract(ContratS150::class, EquipementS150::class, $entityManager);                    
                    break;
                case 'S160':
                    $this->newContract(ContratS160::class, EquipementS160::class, $entityManager);                    
                    break;
                case 'S170':
                    $this->newContract(ContratS170::class, EquipementS170::class, $entityManager);                   
                    break;
                
                default:
                    break;
            }
        }

        dump($clientSelectedInformations);
        dump($formContrat);
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

    public function newContract($entityContrat, $entityEquipement, $entityManager)
    {
        $contrat = new $entityContrat;
        

        $contrat->setNumeroContrat($_POST['numero_contrat']);
        $contrat->setContactId($_POST['contact_id']);
        $contrat->setIdContact($_POST['contact_id']);
        $contrat->setDateSignature($_POST['date_signature']);
        $contrat->setValorisation($_POST['type_valorisation'][0]);
        $contrat->setNombreEquipement($_POST['nombre_equipements'][0]);
        $contrat->setNombreVisite($_POST['nombre_visite']);
        $contrat->setDatePrevisionnelle1($_POST['date_previsionnelle']);
        // tell Doctrine you want to (eventually) save the Product (no queries yet)
        // $entityManager->persist($contrat);
        // // actually executes the queries (i.e. the INSERT query)
        // $entityManager->flush();

        // switch ($_POST['visite_equipement']) {
        //     case 'Nécessite 1 visite par an':
        //         for ($i=0; $i < $_POST['nombre_equipements'] ; $i++) { 
        //             $equipement = new $entityEquipement;
        //             $equipement->setIdContact($_POST['contact_id']);
        //             $equipement->setLibelleEquipement($_POST['type_equipement']);
        //             $equipement->setModeFonctionnement($_POST['mode_fonctionnement']);
        //             if ($_POST['nombre_visite'] == 1) {
        //                 $equipement->setVisite('CEA');
        //             }
        //             else{
        //                 $equipement->setVisite('CE1');
        //             }
        //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
        //             $entityManager->persist($equipement);
        //             // actually executes the queries (i.e. the INSERT query)
        //             $entityManager->flush();
        //         }
        //         break;
        //     case 'Nécessite 2 visites par an':
        //         for ($i=0; $i < $_POST['nombre_equipements'] ; $i++) {
        //             //Création de la CE1 
        //             $equipementCE1 = new $entityEquipement;
        //             $equipementCE1->setIdContact($_POST['contact_id']);
        //             $equipementCE1->setLibelleEquipement($_POST['type_equipement']);
        //             $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement']);
        //             $equipementCE1->setVisite('CE1');
        //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
        //             $entityManager->persist($equipementCE1);
        //             // actually executes the queries (i.e. the INSERT query)
        //             $entityManager->flush();
        //         }
        //         for ($i=0; $i < $_POST['nombre_equipements'] ; $i++) {
        //             //Création de la CE2 
        //             $equipementCE2 = new $entityEquipement;
        //             $equipementCE2->setIdContact($_POST['contact_id']);
        //             $equipementCE2->setLibelleEquipement($_POST['type_equipement']);
        //             $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement']);
        //             $equipementCE2->setVisite('CE1');
        //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
        //             $entityManager->persist($equipementCE2);
        //             // actually executes the queries (i.e. the INSERT query)
        //             $entityManager->flush();
        //         }
        //         break;
        //     case 'Nécessite 3 visites par an':
        //         for ($i=0; $i < $_POST['nombre_equipements'] ; $i++) {
        //             //Création de la CE1 
        //             $equipementCE1 = new $entityEquipement;
        //             $equipementCE1->setIdContact($_POST['contact_id']);
        //             $equipementCE1->setLibelleEquipement($_POST['type_equipement']);
        //             $equipementCE1->setModeFonctionnement($_POST['mode_fonctionnement']);
        //             $equipementCE1->setVisite('CE1');
        //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
        //             $entityManager->persist($equipementCE1);
        //             // actually executes the queries (i.e. the INSERT query)
        //             $entityManager->flush();
        //         }
        //         for ($i=0; $i < $_POST['nombre_equipements'] ; $i++) {
        //             //Création de la CE2 
        //             $equipementCE2 = new $entityEquipement;
        //             $equipementCE2->setIdContact($_POST['contact_id']);
        //             $equipementCE2->setLibelleEquipement($_POST['type_equipement']);
        //             $equipementCE2->setModeFonctionnement($_POST['mode_fonctionnement']);
        //             $equipementCE2->setVisite('CE1');
        //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
        //             $entityManager->persist($equipementCE2);
        //             // actually executes the queries (i.e. the INSERT query)
        //             $entityManager->flush();
        //         }
        //         for ($i=0; $i < $_POST['nombre_equipements'] ; $i++) {
        //             //Création de la CE3 
        //             $equipementCE3 = new $entityEquipement;
        //             $equipementCE3->setIdContact($_POST['contact_id']);
        //             $equipementCE3->setLibelleEquipement($_POST['type_equipement']);
        //             $equipementCE3->setModeFonctionnement($_POST['mode_fonctionnement']);
        //             $equipementCE3->setVisite('CE1');
        //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
        //             $entityManager->persist($equipementCE3);
        //             // actually executes the queries (i.e. the INSERT query)
        //             $entityManager->flush();
        //         }
        //         break;
        //     default:
        //         # code...
        //         break;
        // }


        // Contrat
        // ["numero_contrat"]=> string(7) "1597845" 
        // ["date_signature"]=> string(10) "2025-03-20" 
        // ["duree"]=> string(1) "2" 
        // ["tacite_reconduction_non"]=> string(3) "non" 
        // ["type_valorisation"]=> array(1) { [0]=> string(4) "2,5%" } 
        // ["nombre_equipements"]=> array(1) { [0]=> string(2) "25" } 
        // ["nombre_visite"]=> string(1) "2" 
        // ["date_previsionnelle"]=> string(10) "2025-03-27" 

        // Equipement
        // ["type_equipement"]=> array(1) { [0]=> string(18) "Rideau métallique" } 
        // ["mode_fonctionnement"]=> array(1) { [0]=> string(9) "Motorisé" } 
        // ["visite_equipement"]=> array(1) { [0]=> string(27) "Nécessite 2 visites par an" } 
        // ["submit_contrat"]=> string(0) ""

        return $contrat;
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
}
