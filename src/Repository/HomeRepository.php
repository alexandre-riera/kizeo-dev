<?php

namespace App\Repository;

use stdClass;
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
    public function getListOfPdf($clientSelected, $visite, $agenceSelected)
    {
        $yearsArray = [2024, 2025, 2026, 2027, 2028, 2029, 2030];
        $agenceSelected = trim($agenceSelected);
        $clientSelected = str_replace(" ", "_", $clientSelected);
        $results = [];

        // Connexion FTP
        $ftp_server = $_ENV['FTP_SERVER'];
        $ftp_user_name = $_ENV['FTP_USERNAME'];
        $ftp_user_pass = $_ENV['FTP_PASSWORD'];

        $conn_id = ftp_connect($ftp_server);
        if ($conn_id === false) {
            echo "Impossible de se connecter au serveur FTP";
            return $results;
        }

        if (ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)) {
            // foreach ($yearsArray as $year) {
            //     $remotePath = $agenceSelected . "/" . $clientSelected . "/" . $year . "/" . $visite;
            //     if (ftp_chdir($conn_id, $remotePath)) {
            //         $files = ftp_nlist($conn_id, ".");
            //         foreach ($files as $file) {
            //             if (preg_match("#\.(pdf)$#i", $file)) {
            //                 $myFile = new stdClass;
            //                 $myFile->path = $file;
            //                 $myFile->annee = $year;
            //                 if (!in_array($myFile, $results)) {
            //                     array_push($results, $myFile);
            //                 }
            //             }
            //         }
            //     } else {
            //         echo "Dossier distant non trouvé pour l'année " . $year . " : " . $remotePath;
            //         continue;
            //     }
            // }
            // foreach ($yearsArray as $year) {
                $directoryPath = "/{$agenceSelected}/{$clientSelected}/2025/{$visite}";
    
                // Changer de répertoire sur le serveur FTP
                // if ($directoryPath) {
                    // Récupérer la liste des fichiers PDF
                    $files = ftp_nlist($conn_id, '.');
                    foreach ($files as $file) {
                        if (preg_match("#\.(pdf)$#i", $file)) {
                            $myFile = new stdClass;
                            $myFile->path = $directoryPath . '/' . $file; // Donne le chemin complet du fichier
                            $myFile->annee = 2025;
    
                            if (!in_array($myFile, $results)) {
                                array_push($results, $myFile);
                            }
                        }
                    }
                // } else {
                    // Optionnel : journaliser ou gérer le fait que le répertoire n'existe pas
                    // Vous pouvez ajouter une ligne ici pour loguer la tentative d'accès à un répertoire inexistant
                    // error_log("Directory does not exist: " . $directoryPath);
                // }
            // }
            ftp_close($conn_id);
        } else {
            echo "Impossible de se connecter au serveur FTP avec les identifiants fournis";
        }

        return $results;
    }
}