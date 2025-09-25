<?php

namespace App\Service;

use stdClass;
use Symfony\Component\HttpFoundation\Request;
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

    public function getIdListContact($agence):string
    {
        $listId = "";

        switch ($agence) {
            case 'S10':
                $listId = $_ENV['TEST_CLIENTS_GROUP'];
                break;
            case 'S40':
                $listId = $_ENV['TEST_CLIENTS_ST_ETIENNE'];
                break;
            case 'S50':
                $listId = $_ENV['TEST_CLIENTS_GRENOBLE'];
                break;
            case 'S60':
                $listId = $_ENV['TEST_CLIENTS_LYON'];
                break;
            case 'S70':
                $listId = $_ENV['TEST_CLIENTS_BORDEAUX'];
                break;
            case 'S80':
                $listId = $_ENV['TEST_CLIENTS_PARIS_NORD'];
                break;
            case 'S100':
                $listId = $_ENV['TEST_CLIENTS_MONTPELLIER'];
                break;
            case 'S120':
                $listId = $_ENV['TEST_CLIENTS_HAUTS_DE_FRANCE'];
                break;
            case 'S130':
                $listId = $_ENV['TEST_CLIENTS_TOULOUSE'];
                break;
            case 'S140':
                $listId = $_ENV['TEST_CLIENTS_EPINAL'];
                break;
            case 'S150':
                $listId = $_ENV['TEST_CLIENTS_PACA'];
                break;
            case 'S160':
                $listId = $_ENV['TEST_CLIENTS_ROUEN'];
                break;
            case 'S170':
                $listId = $_ENV['TEST_CLIENTS_RENNES'];
                break;
            
            default:
                return "L'agence sélectionnée n'appartient pas à SOMAFI. Voir la fonction getIdListContact ligne 87 du service KizeoService";
                break;
        }

        return $listId;
    }

    public function stringToContactObject(string $contactString)
    {
        
        $fields = explode('|', $contactString);
        $fieldsSplitted = [];
        
        foreach ($fields as $contactPart) {
            $fieldsSplitted [] = explode(':', $contactPart);
        }
        
        foreach ($fieldsSplitted as $contactPartSplitted) {
            $contactObject = new stdClass();
            $contactObject -> raison_sociale = $fieldsSplitted[0][1];
            $contactObject -> code_postal = $fieldsSplitted[1][1];
            $contactObject -> ville = $fieldsSplitted[2][1];
            $contactObject -> id_contact = $fieldsSplitted[3][1];
            $contactObject -> agence = $fieldsSplitted[4][1];
            if (count($fieldsSplitted) == 5) {
                return $contactObject;
            }
            else{
                if ($fieldsSplitted[5]) {
                    $contactObject -> id_societe = $fieldsSplitted[5][1];
                }
                else{
                    $contactObject -> id_societe = "";
                }
                if (count($fieldsSplitted) == 6) {
                    return $contactObject;
                }
                else{
                    $contactObject -> equipement_supp_1 = $fieldsSplitted[6][1];
                    if (count($fieldsSplitted) == 7) {
                        $contactObject -> equipement_supp_2 = "";
                    }
                    else{
                        $contactObject -> equipement_supp_2 = $fieldsSplitted[7][1];
                    }
                }
            }
            
            return $contactObject; 
        }
    }

    public function contactToString(array $contact): string
    {
        return implode('|', $contact);
    }

    public function updateListContactOnKizeo(string $idListContact, string $updateContactName, string $contactStringToUpload): void
    {
        // Récupérer la liste actuelle depuis Kizeo
        $currentKizeoList = $this->getKizeoList($idListContact);

        // Si une des lignes de $currentKizeoList commence par le début de la valeur de $updateContactName, je la remplace par $contactStringToUpload
        $newListUpdatedToUpload = [];
        $replaced = false;

        foreach ($currentKizeoList as $oldContact) {
            // On est obligé de faire 2 fois un explode sur le résultat de kizeo car après le 1er (|), 
            // on obtient "nom:nom" donc on explode à nouveau avec un : pour obtenir "nom"
            if (explode('|', explode(':', $oldContact)[0])[0] == explode('|', $contactStringToUpload)[0]) {
                $newListUpdatedToUpload[] = $contactStringToUpload;
                $replaced = true;
            } else {
                $newListUpdatedToUpload[] = $oldContact;
            }
        }
        // Si aucune ligne n'a été remplacée, ajouter la nouvelle ligne
        if (!$replaced) {
            $newListUpdatedToUpload[] = $contactStringToUpload;
        }

        // Envoi de la requête PUT à Kizeo
        Request::enableHttpMethodParameterOverride();
        $response = $this->client->request(
            'PUT',
            'https://forms.kizeo.com/rest/v3/lists/' . $idListContact,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'json' => [
                    'items' => $newListUpdatedToUpload,
                ],
            ]
        );
    }

    private function getKizeoList(string $idListContact): array
    {
        $contactsArrayList = [];

        $response = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/lists/' .  $idListContact, [
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
}