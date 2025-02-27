<?php

namespace App\Controller;

use App\Service\KizeoService;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    private $kizeoService;

    public function __construct(KizeoService $kizeoService)
    {
        $this->kizeoService = $kizeoService;
    }

    /**
     * ADD new contact in BDD and Kizeo
     */
    #[Route('/contact/new', name: 'app_contact_new', methods: ['GET', 'POST'])]
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
            }
        }
        dump($contactSelectionne);
        dump($contactName);
        dump($contactId);
        dump($contactAgence);

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

        // $contactsById = [];
        // foreach ($contactsFromKizeoSplittedInObject as $contactObject) {
        //     $contactsById[$contactObject->id_contact] = $contactObject;
        // }
        
        // if (isset($contactsById[$contactId])) {
        //     $contactObject = $contactsById[$contactId];
        //     dump($contactObject);
        //     $contactCodePostal = $contactObject->code_postal;
        //     $contactVille = $contactObject->ville;
        //     if (isset($contactObject->id_societe)) {
        //         $contactIdSociete = $contactObject->id_societe;
        //     }
        //     if (isset($contactObject->equipement_supp_1)) {
        //         $contactEquipSupp1 = $contactObject->equipement_supp_1;
        //     }
        //     if (isset($contactObject->equipement_supp_2)) {
        //         $contactEquipSupp2 = $contactObject->equipement_supp_2;
        //     }
        // }
        foreach ($contactsFromKizeoSplittedInObject as $contactObject) {
            if ($contactObject->id_contact == $contactId) {
                $contactCodePostal = $contactObject->code_postal;
                $contactVille = $contactObject->ville;
                if (isset($contactObject->id_societe)) {
                    $contactIdSociete = $contactObject->id_societe;
                }
                if (isset($contactObject->equipement_supp_1)) {
                    $contactEquipSupp1 = $contactObject->equipement_supp_1;
                }
                if (isset($contactObject->equipement_supp_2)) {
                    $contactEquipSupp2 = $contactObject->equipement_supp_2;
                }
                break; // Sortir de la boucle
            }
        }
        // $clientId = $request->request->get('client');
        // if ($clientId) {
        //     // Récupérer le contact sélectionné depuis $contactsKizeo
        //     foreach ($contactsKizeo as $contactString) {
        //         $contactArray = $this->kizeoService->StringToContactObject($contactString);
        //         if ($contactArray['id_contact'] == $clientId) {
        //             $contact = $contactArray;
        //             break;
        //         }
        //     }
        // }

        // if ($request->isMethod('POST') && $request->request->has('submit')) {
        //     $nouveauContact = [
        //         'Raison_sociale' => $request->request->get('Raison_sociale'),
        //         'Code_postale' => $request->request->get('Code_postale'),
        //         'Ville' => $request->request->get('Ville'),
        //         'id_contact' => $request->request->get('id_contact'),
        //         'Agence' => $agenceSelectionnee,
        //         'id_société' => $request->request->get('id_société'),
        //         'Équipements' => $request->request->get('Équipements'),
        //         'Équipements_complémentaires' => $request->request->get('Équipements_complémentaires'),
        //     ];

        //     // Mettre à jour ou ajouter le contact dans $contactsKizeo
        //     $contactExiste = false;
        //     foreach ($contactsKizeo as $key => $contactString) {
        //         $contactArray = $this->kizeoService->StringToContactObject($contactString);
        //         if ($contactArray['id_contact'] == $nouveauContact['id_contact']) {
        //             $contactsKizeo[$key] = $this->kizeoService->contactToString($nouveauContact);
        //             $contactExiste = true;
        //             break;
        //         }
        //     }
        //     if (!$contactExiste) {
        //         $contactsKizeo[] = $this->kizeoService->contactToString($nouveauContact);
        //     }
        //     $this->kizeoService->sendContacts($agenceSelectionnee, $contactsKizeo);
            
        //     $this->addFlash('success', 'Contact mis à jour/créé avec succès !');
            
        //     return $this->redirectToRoute('app_contact_new');
        // }
        
        return $this->render('contact/index.html.twig', [
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