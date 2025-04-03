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
    
    public function getListOfPdf($clientSelected, $visitArray, $agenceSelected, $dateEnregistrementEquipement)
    {
        $baseDir = 'https://www.pdf.somafi-group.fr/' . trim($agenceSelected) . '/' . str_replace(" ", "_", $clientSelected);
        $results = [];
        foreach ($visitArray as $visite) {
            $file = str_replace(" ", "_", $clientSelected) . '-' . date("d-m-Y" , strtotime($dateEnregistrementEquipement)) . '-' . $visite . '.pdf';
            $myFile = new stdClass;
            $myFile->path = $baseDir . '/' . date("Y", strtotime($dateEnregistrementEquipement)) . '/' . $visite . '/' . $file;
            $myFile->annee = date("Y", strtotime($dateEnregistrementEquipement));
            
            if (!in_array($myFile, $results)) {
                array_push($results, $myFile);
            }
        }
        dump($results);
        // $baseDir = 'https://www.pdf.somafi-group.fr/' . trim($agenceSelected) . '/' . str_replace(" ", "_", $clientSelected);
        // $results = [];

        // // Récupérer la liste des années disponibles
        // $yearDirs = $this->getYearDirectories($baseDir);

        // // Parcourir les années et les visites
        // foreach ($yearDirs as $year) {
        //     dump($baseDir);
        //     dump($clientSelected);
        //     dump($year);
        //     dump($visite);
        //     dump($agenceSelected);
        //     $visitDir = $baseDir . '/' . $year . '/' . $visite;
        //     dump($visitDir);
        //     // Vérifier si le répertoire de la visite existe
        //     if ($this->directoryExists($visitDir)) {
        //         dump("Hello I'm HERE !");
        //         // Récupérer les fichiers PDF dans le répertoire de la visite
        //         $pdfFiles = $this->getPdfFiles($visitDir);
        //         dump($pdfFiles);
        //         // Ajouter les fichiers PDF à la liste des résultats
        //         foreach ($pdfFiles as $pdfFile) {
        //             $results[] = [
        //                 'year' => $year,
        //                 'visit' => $visite,
        //                 'file' => $pdfFile
        //             ];
        //         }
        //     }
        // }

        return $results;
    }

    private function getYearDirectories($baseDir)
    {
        $yearDirs = [];
        foreach (range(date('Y'), date('Y') + 10) as $year) {
            if ($this->directoryExists($baseDir . '/' . $year)) {
                $yearDirs[] = $year;
            }
        }
        return $yearDirs;
    }

    private function directoryExists($path)
    {
        try {
            $contents = file_get_contents($path);
            return $contents !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getPdfFiles($path)
    {
        $pdfFiles = [];
        $contents = $this->directoryContents($path);
        if (is_array($contents)) {
            foreach ($contents as $item) {
                if (substr($item, -4) === '.pdf') {
                    $pdfFiles[] = $item;
                }
            }
        }
        return $pdfFiles;
    }

    private function directoryContents($path)
    {
        $ftpServer = $_ENV['FTP_SERVER'];
        $ftpUsername = $_ENV['FTP_USERNAME'];
        $ftpPassword = $_ENV['FTP_PASSWORD'];

        try {
            $ftpConnection = ftp_connect($ftpServer);
            if ($ftpConnection && ftp_login($ftpConnection, $ftpUsername, $ftpPassword)) {
                $contents = ftp_nlist($ftpConnection, $path);
                ftp_close($ftpConnection);
                return $contents;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

}