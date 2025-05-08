<?php

namespace App\Repository;

use App\Entity\ContactsCC;
use stdClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Repository de la page home
 */
class GlsRepository{
    public function __construct(private HttpClientInterface $client)
    {
        
    }

    public function getListClientFromKizeoById(int $id, $entityManager, $contactsCCRepository){
        
        $allContactsCC = $contactsCCRepository->findall();
        $listClientsGlsFromKizeo = []; // They are from KIZEO with his id_contact, raison_sociale and code_agence
        $glsContactsFromBdd = []; // They are from BDD
        $kizeoContactsSplitted = []; // [0][1] Raison sociale, [2][3] Code postal, [4][5] ville, [6][7] id contact, [8][9] Code agence, [10][11] id societe

        // 1) We get contact on Kizeo from agency ids
        $response = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/lists/'.$id, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]
        );
        $content = $response->getContent();
        $content = $response->toArray();
        $content = $content['list']['items'];
        
        // 2) We split these contacts strings from $content and push results in $kizeoContactsSplitted
        // ex: "ACIM HYDRO:ACIM HYDRO|42400:42400|ST CHAMOND:ST CHAMOND|5420:5420|S40:S40|5368:5368" (At first)
        // And then :
        // 0 => "ACIM HYDRO" 1 => "ACIM HYDRO" 2 => "42400" 3 => "42400" 4 => "ST CHAMOND" 5 => "ST CHAMOND" 6 => "5420" 7 => "5420" 8 => "S40" 9 => "S40" 10 => "5368" 11 => "5368"]
        foreach ($content as $client) {
            array_push($kizeoContactsSplitted, preg_split("/[:|]/",$client));
        }
        
        // We iterate over $kizeoContactsSplitted array from KIZEO to sort only GLS contacts
        foreach ($kizeoContactsSplitted as $clientFiltered) {
            // If it's a GLS contact
            if (str_contains($clientFiltered[0],"GLS")) {
                // On push et concatene avec un "-" l'id contact, la raison sociale et le code agence
                // EX : 3239-GLS  ANDREZIEUX-S40
                // array_push($listClientsGlsFromKizeo, $clientFiltered[6] . "-" . $clientFiltered[0] . " - " . $clientFiltered[8]);
                
                // 3) We create a new object, client, with his id_contact, raison_sociale and code_agence
                // We push him in $listClientGls array
                $clientFromKizeoShortened = new stdClass;
                $clientFromKizeoShortened->id_contact = $clientFiltered[6];
                $clientFromKizeoShortened->raison_sociale = $clientFiltered[0];
                $clientFromKizeoShortened->code_agence = $clientFiltered[8];
                $listClientsGlsFromKizeo [] = $clientFromKizeoShortened;
            }    
        }

        // If $allContactsCC from BDD is not empty else return false
        if (isset($allContactsCC)) {
            // We iterate over $allContactsCC
            foreach ($allContactsCC as $contactCC) {
                // If they are GLS contacts, we store them into $glsContactsFromBdd array
                if (str_contains($contactCC->getRaisonSocialeContact(),"GLS")) {
                    $glsContactsFromBdd [] = $contactCC;
                }
            }
        }

        // 4) We iterate over $listClientsGlsFromKizeo with his id_contact, raison_sociale and code_agence
        // If their Id are NOT into $glsContactsFromBdd, so they don't exist yet, we create a new ContactsCC
        // Création d'un tableau associatif pour stocker les IDs des contacts en BDD
        $bddContactsIds = [];
        foreach ($glsContactsFromBdd as $bddGls) {
            $bddContactsIds[$bddGls->getIdContact()] = true;
        }
        foreach ($listClientsGlsFromKizeo as $kizeoGls) {
            $idContact = $kizeoGls->id_contact;
            
            // Vérification si l'ID existe déjà dans le tableau des IDs en BDD
            if (!isset($bddContactsIds[$idContact])) {
                // Si l'ID n'existe pas, on crée le nouveau contact
                $contactGls = new ContactsCC();
                $contactGls->setIdContact($kizeoGls->id_contact);
                $contactGls->setRaisonSocialeContact($kizeoGls->raison_sociale);
                $contactGls->setCodeAgence($kizeoGls->code_agence);
                $entityManager->persist($contactGls);
                $entityManager->flush();
            }
        }
        return $listClientsGlsFromKizeo;
    }

    public function getListOfPdf($clientSelected, $visite, $agenceSelected){
        // I add 2024 in the url cause we are in 2025 and there is not 2025 folder yet
        // MUST COMPLETE THIS WITH 2024 AND 2025 TO LIST PDF FILES IN FOLDER
        $yearsArray = [2024, 2025, 2026, 2027, 2028,2029, 2030];
        $agenceSelected = trim($agenceSelected);
        $results = [];
        if(is_dir("../public/uploads/documents_cc/$clientSelected")){
            $directoriesLists = scandir( "../public/uploads/documents_cc/$clientSelected" );
            foreach($directoriesLists as $fichier){
                if (!in_array($fichier, $results) && $fichier != '.' && $fichier != '..') {
                    array_push($results, $fichier);
                }
            }
        }
        return $results;
    }
}