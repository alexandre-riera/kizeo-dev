<?php

namespace App\Service;

use stdClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KizeoService
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function getContacts(string $agence): array
    {
        // Implémentez la logique pour récupérer les contacts de l'agence via l'API Kizeo Forms
        // Utilisez $this->client->request('GET', ...)
        // Retournez un tableau de strings, chaque string représentant un contact
        $listId = 0;
        $contactsArrayList = [];

        switch ($agence) {
            case 'Group':
                $listId = $_ENV['TEST_CLIENTS_GROUP'];
                break;
            case 'St Etienne':
                $listId = $_ENV['TEST_CLIENTS_ST_ETIENNE'];
                break;
            case 'Grenoble':
                $listId = $_ENV['TEST_CLIENTS_GRENOBLE'];
                break;
            case 'Lyon':
                $listId = $_ENV['TEST_CLIENTS_LYON'];
                break;
            case 'Bordeaux':
                $listId = $_ENV['TEST_CLIENTS_BORDEAUX'];
                break;
            case 'Paris Nord':
                $listId = $_ENV['TEST_CLIENTS_PARIS_NORD'];
                break;
            case 'Montpellier':
                $listId = $_ENV['TEST_CLIENTS_MONTPELLIER'];
                break;
            case 'Hauts de France':
                $listId = $_ENV['TEST_CLIENTS_HAUTS_DE_FRANCE'];
                break;
            case 'Toulouse':
                $listId = $_ENV['TEST_CLIENTS_TOULOUSE'];
                break;
            case 'Epinal':
                $listId = $_ENV['TEST_CLIENTS_EPINAL'];
                break;
            case 'PACA':
                $listId = $_ENV['TEST_CLIENTS_PACA'];
                break;
            case 'Rouen':
                $listId = $_ENV['TEST_CLIENTS_ROUEN'];
                break;
            case 'Rennes':
                $listId = $_ENV['TEST_CLIENTS_RENNES'];
                break;
            
            default:
                # code...
                break;
        }

        $response = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/lists/' .  $listId, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]
        );
        $content = $response->getContent();
        $content = $response->toArray();
        array_push($contactsArrayList, $content['list']['items']);
        return $contactsArrayList[0];
    }

    public function sendContacts(string $agence, array $contacts): void
    {
        // Implémentez la logique pour envoyer les contacts mis à jour à Kizeo Forms via l'API
        // Utilisez $this->client->request('POST', ...)
    }

    public function stringToContact(string $contactString)
    {
        
        $fields = explode('|', $contactString);
        $fieldsSplitted = [];
        
        foreach ($fields as $contactPart) {
            $fieldsSplitted [] = explode(':', $contactPart);
        }
        
        foreach ($fieldsSplitted as $contactPartSplitted) {
            $contactObject = new stdClass();
            $contactObject -> raison_sociale = $contactPartSplitted[0];
            $contactObject -> code_postale = $contactPartSplitted[0];
            $contactObject -> ville = $contactPartSplitted[0];
            $contactObject -> id_contact = $contactPartSplitted[0];
            $contactObject -> Agence = $contactPartSplitted[0];
            $contactObject -> id_société = $contactPartSplitted[0];
            
            return $contactObject; 
        }
    }

    public function contactToString(array $contact): string
    {
        return implode('|', $contact);
    }
}