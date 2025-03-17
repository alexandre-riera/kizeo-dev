<?php

namespace App\Controller;

use App\Entity\ContactS10;
use App\Entity\ContactS40;
use App\Entity\ContactS50;
use App\Entity\ContactS60;
use App\Entity\ContactS70;
use App\Entity\ContactS80;
use App\Entity\ContactS100;
use App\Entity\ContactS120;
use App\Entity\ContactS130;
use App\Entity\ContactS140;
use App\Entity\ContactS150;
use App\Entity\ContactS160;
use App\Entity\ContactS170;
use App\Entity\ContratS10;
use App\Entity\ContratS50;
use App\Entity\EquipementS50;
use App\Form\ContratS50Type;
use App\Form\EquipementS50Type;
use App\Service\KizeoService;
use App\Repository\ContratRepositoryS10;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
                    $formContrat = $this->newContract($request, $contactAgence);
                    break;
                case ' S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                   
                    break;
                case ' S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);
                    break;
                case ' S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);
                    break;
                case 'S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case 'S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                case ' S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $contactId]);
                    $formContrat = $this->newContract($request, $contactAgence);                    
                    break;
                
                default:
                    
                    break;
            }

            // GET Contrat informations ---- PUT THIS CALL IN EVERY CASES ADDING HIS PROPER CONTACTAGENCE
            $theAssociatedContract = $contratRepositoryS10->findContratByIdContact($contactId);
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
        ]);
    }

    public function newContract(Request $request, $contactAgence)
    {
        // just set up a fresh $task object (remove the example data)
        switch ($contactAgence) {
            case 'S50':
                $contrat = new ContratS50();
                $formContrat = $this->createForm(ContratS50Type::class, $contrat);
                break;
            
            default:
                # code...
                break;
        }

        $formContrat->handleRequest($request);
        if ($formContrat->isSubmitted() && $formContrat->isValid()) {
            // $formContrat->getData() holds the submitted values
            // but, the original `$contrat` variable has also been updated
            $contrat = $formContrat->getData();
            dd($contrat);
            // ... perform some action, such as saving the contrat to the database

            return new Response('success');
        }

        return $formContrat;
    }

    /**
     * @return equipment new line template used by fetch API for + button
     */
    #[Route('/equipements/new-line', name: 'app_equipement_new_line', methods: ['GET'])]
    public function newLine(): Response
    {
        return $this->render('equipement/_new_line.html.twig');
    }
}
