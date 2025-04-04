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
    
    public function getListOfPdf($clientSelected, $visitArray, $agenceSelected, $dateArray)
    {
        dump($dateArray);
        $baseDir = 'https://www.pdf.somafi-group.fr/' . trim($agenceSelected) . '/' . str_replace(" ", "_", $clientSelected);
        $results = [];
        foreach ($dateArray as $dateDeVisite) {
            foreach ($visitArray as $visite) {
                $file = str_replace(" ", "_", $clientSelected) . '-' . date("d-m-Y" , strtotime($dateDeVisite)) . '-' . $visite . '.pdf';
                $myFile = new stdClass;
                $myFile->path = $baseDir . '/' . date("Y", strtotime($dateDeVisite)) . '/' . $visite . '/' . $file;
                $myFile->annee = date("Y", strtotime($dateDeVisite));
                
                if (!in_array($myFile, $results)) {
                    array_push($results, $myFile);
                }
            }
        }
        dump($results);

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