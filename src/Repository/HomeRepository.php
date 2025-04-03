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
        $baseDir = 'https://www.pdf.somafi-group.fr/' . trim($agenceSelected) . '/' . str_replace(" ", "_", $clientSelected);
        $results = [];

        // Récupérer la liste des années disponibles
        $yearDirs = $this->getYearDirectories($baseDir);

        // Parcourir les années et les visites
        foreach ($yearDirs as $year) {
            dump($baseDir);
            dump($clientSelected);
            dump($year);
            dump($visite);
            dump($agenceSelected);
            $visitDir = $baseDir . '/' . $year . '/' . $visite;
            dump($visitDir);
            // Vérifier si le répertoire de la visite existe
            if ($this->directoryExists($visitDir)) {
                dump("Hello I'm HERE !");
                // Récupérer les fichiers PDF dans le répertoire de la visite
                $pdfFiles = $this->getPdfFiles($visitDir);
                dump($pdfFiles);
                // Ajouter les fichiers PDF à la liste des résultats
                foreach ($pdfFiles as $pdfFile) {
                    $results[] = [
                        'year' => $year,
                        'visit' => $visite,
                        'file' => $pdfFile
                    ];
                }
            }
        }

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
        foreach ($contents as $item) {
            if (substr($item, -4) === '.pdf') {
                $pdfFiles[] = $item;
            }
        }
        return $pdfFiles;
    }

    private function directoryContents($path)
    {
        try {
            return scandir($path);
        } catch (\Exception $e) {
            return [];
        }
    }

}