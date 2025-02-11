<?php

namespace App\Repository;

use App\Entity\ContactsCC;
use stdClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Repository de la page home
 */
class KuehneRepository{
    public function __construct(private HttpClientInterface $client)
    {
        
    }

    public function getListClientFromKizeoById(int $id, $entityManager, $contactsCCRepository){
        
        // Return only Kuehne contacts
        $allContactsCC = $contactsCCRepository->findall();
        dump($allContactsCC);
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
        $listSplitted = [];
        $listClientsKuehne = [];
        $kuehneContacts = [];
        $kuehneIds = [];
        foreach ($content as $client) {
            array_push($listSplitted, preg_split("/[:|]/",$client));
        }
        foreach ($listSplitted as $clientFiltered) {
            if (str_contains($clientFiltered[0],"KUEHNE") || str_contains($clientFiltered[0],"KN ")) {
                // On push et concatene avec un "-" l'id contact, la raison sociale et le code agence
                // EX : 3239-KUEHNE  ANDREZIEUX-S40
                array_push($listClientsKuehne, $clientFiltered[6] . "-" . $clientFiltered[0] . " - " . $clientFiltered[8]);
                // if (!in_array($clientFiltered[6], $kuehneIds)) {
                    $kuehneIds [] = $clientFiltered[6];
                // }
                

                // Si l'id contact n'est pas présent dans le tableau $allContactsCC, on crée un nouveau ContactCC
                // 39 contact ont déjà été créés sans le if de mit en place
                // if (count($allContactsCC) != 0) {
                //     foreach ($allContactsCC as $contactCC) {
                //         if (str_contains($contactCC->getRaisonSocialeContact(),"KUEHNE") || str_contains($contactCC->getRaisonSocialeContact(),"KN ")) {
                //             $kuehneContacts [] = $contactCC;
                //         }
                //         if (!in_array($contactCC->getIdContact(), $kuehneIds)) {
                //             $contactKuehne = new ContactsCC();
                //             $contactKuehne->setIdContact($clientFiltered[6]);
                //             $contactKuehne->setRaisonSocialeContact($clientFiltered[0]);
                //             $contactKuehne->setCodeAgence($clientFiltered[8]);
                //              // tell Doctrine you want to (eventually) save the Product (no queries yet)
                //             $entityManager->persist($contactKuehne);
                //             // actually executes the queries (i.e. the INSERT query)
                //             $entityManager->flush();
                //         }
                //     }
                // }
            }
        }
        dump($listClientsKuehne);
        
        dump($kuehneContacts);
        dd(count($kuehneIds));
        return $listClientsKuehne;
    }

    public function getListOfPdf($clientSelected, $visite, $agenceSelected){
        // I add 2024 in the url cause we are in 2025 and there is not 2025 folder yet
        // MUST COMPLETE THIS WITH 2024 AND 2025 TO LIST PDF FILES IN FOLDER
        $yearsArray = [2024, 2025, 2026, 2027, 2028,2029, 2030];
        $agenceSelected = trim($agenceSelected);
        $results = [];
        foreach ($yearsArray as $year) {
            if(is_dir("../pdf/maintenance/$agenceSelected/$clientSelected/$year/$visite")){
                $directoriesLists = scandir( "../pdf/maintenance/$agenceSelected/$clientSelected/$year/$visite" );
                foreach($directoriesLists as $fichier){

                    if(preg_match("#\.(pdf)$#i", $fichier)){
                        
                        $myFile = new stdClass;
                        $myFile->path = $fichier;
                        $myFile->annee = $year;
                        //la preg_match définie : \.(jpg|jpeg|png|gif|bmp|tif)$
                        
                        //Elle commence par un point "." (doit être échappé avec anti-slash \ car le point veut dire "tous les caractères" sinon)
                        
                        //"|" parenthèses avec des barres obliques dit "ou" (plusieurs possibilités : jpg ou jpeg ou png...)
                        
                        //La condition "$" signifie que le nom du fichier doit se terminer par la chaîne spécifiée. Par exemple, un fichier nommé 'monFichier.jpg.php' ne sera pas accepté, car il ne se termine pas par '.jpg', '.jpeg', '.png' ou toute autre extension souhaitée.
                        
                        if (!in_array($myFile, $results)) {
                            array_push($results, $myFile);
                        }
                    }
                }
            }
        }
        
        return $results;
    }
}