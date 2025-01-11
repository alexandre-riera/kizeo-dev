<?php

namespace App\Repository;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Repository de la page home
 */
class HomeRepository{
    public function __construct(private HttpClientInterface $client)
    {
        
    }

    public function getListClientFromKizeoById(int $id){
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
        $listClientsFiltered = [];
        foreach ($content as $client) {
            array_push($listSplitted, preg_split("/[:|]/",$client));
        }
        foreach ($listSplitted as $clientFiltered) {
            array_push($listClientsFiltered, $clientFiltered[6] . "-" . $clientFiltered[0] . " - " . $clientFiltered[8]);
        }
        return $listClientsFiltered;
    }

    public function getListOfPdf($clientSelected, $visite, $agenceSelected){
        // I add 2024 in the url cause we are in 2025 and there is not 2025 folder yet
        // MUST COMPLETE THIS WITH 2024 AND 2025 TO LIST PDF FILES IN FOLDER
        $thisYear = date('Y');
        $agenceSelected = trim($agenceSelected);
        $directoriesLists = scandir( "../pdf/maintenance/$agenceSelected/$clientSelected/2024/$visite" );
        $results = [];
        foreach($directoriesLists as $fichier){

            if(preg_match("#\.(pdf)$#i", $fichier)){
                
                //la preg_match définie : \.(jpg|jpeg|png|gif|bmp|tif)$
                
                //Elle commence par un point "." (doit être échappé avec anti-slash \ car le point veut dire "tous les caractères" sinon)
                
                //"|" parenthèses avec des barres obliques dit "ou" (plusieurs possibilités : jpg ou jpeg ou png...)
                
                //La condition "$" signifie que le nom du fichier doit se terminer par la chaîne spécifiée. Par exemple, un fichier nommé 'monFichier.jpg.php' ne sera pas accepté, car il ne se termine pas par '.jpg', '.jpeg', '.png' ou toute autre extension souhaitée.
                
                if (!in_array($fichier, $results)) {
                    array_push($results, $fichier);
                }
            }
        }
        return $results;
    }

    
}