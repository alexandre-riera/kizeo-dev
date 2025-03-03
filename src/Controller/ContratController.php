<?php

namespace App\Controller;

use App\Service\KizeoService;
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

    #[Route('/contrat/new', name: 'app_contrat', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
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

        // ID de la liste contact à passer à la fonction updateListContactOnKizeo($idListContact)
        $idListContact = "";

        $agenceSelectionnee = "";
        if(isset($_POST['submit_agence'])){  
            if(!empty($_POST['agence'])) {  
                $agenceSelectionnee = $_POST['agence'];
            } 
        }
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
        ]);
    }
}
