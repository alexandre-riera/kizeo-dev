<?php

namespace App\Repository;

use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  private HttpClientInterface $client)
    {
        parent::__construct($registry, Equipement::class);
    }

    /**
    * @return Equipement[] Returns an array of ONLY "EQUIPEMENT CONTRAT 38"
    */
   public function getEquipements(): array
   {
        $equipementsArray = [];
        $equipementsSplittedArray = [];

        // ----------------------------------------------------  Requête de toutes les listes sur Kizeo
        $reqAllLists = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/lists', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]
        );
        $contentAllLists = $reqAllLists->getContent();
        $contentAllLists = $reqAllLists->toArray();
        $contentAllLists = $contentAllLists['lists'];
        // dd($contentAllLists); All lists on Kizeo
        // ------------------------------------------------------  Mettre la boucle for des ids ici
        foreach ($contentAllLists as $key => $value) {
            // ------------------------------------------------------TO REQUEST ONLY "EQUIPEMENT CONTRAT 38"-------------------------
            if ($contentAllLists[$key]['class'] === 'Maintenance' && $contentAllLists[$key]['id'] != '409466') {
                // dump("Liste d'ID équipements contrat 38");
                // dump($contentAllLists[$key]['id']);
                $response = $this->client->request(
                    'GET',
                    'https://forms.kizeo.com/rest/v3/lists/' . $contentAllLists[$key]['id'], [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );
                
                $content = $response->getContent();
                $content = $response->toArray();
                
                
                $equipementsArray = array_map(null, $content['list']['items']);
                for ($i=0; $i < count($equipementsArray) ; $i++) {
                    if (isset($equipementsArray[$i])) {
                        array_push($equipementsSplittedArray, array_unique(preg_split("/[:|]/", $equipementsArray[$i])));
                    }
                }
            }
        }
        return $equipementsSplittedArray;
   }
   public function getEquipementsGrenoble(): array
   {

        // ----------------------------------------------------  Requête des equipements au contrat Grenoble sur Kizeo
        $reqlistEquipementsContratGrenoble = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/lists/414025', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]
        );
        $EquipementsGrenoble = $reqlistEquipementsContratGrenoble->getContent();
        $EquipementsGrenoble = $reqlistEquipementsContratGrenoble->toArray();
        // ------------------------------------------------------  Mettre la boucle for des ids ici
        
        return $EquipementsGrenoble;
   }
   // ---------------------------------------------- ROUTE POUR LES PORTAILS NE SERVANT PAS POUR L'INSTANT --------------------------------------
//     /**
//     * @return Equipement[] Returns an array of Contact objects
//     */
//    public function getPortails(): array
//    {
//         $portailsArray = [];
//         $portailsSplittedArray = [];

//         // ----------------------------------------------------  Requête de toutes les listes sur Kizeo
//         $reqAllLists = $this->client->request(
//             'GET',
//             'https://forms.kizeo.com/rest/v3/lists', [
//                 'headers' => [
//                     'Accept' => 'application/json',
//                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
//                 ],
//             ]
//         );
//         $contentAllLists = $reqAllLists->getContent();
//         $contentAllLists = $reqAllLists->toArray();
//         $contentAllLists = $contentAllLists['lists'];
//         // dd($contentAllLists); All lists on Kizeo
//         // ------------------------------------------------------  Mettre la boucle for des ids ici
//         foreach ($contentAllLists as $key => $value) {
//             // ------------------------------------------------------TO REQUEST ONLY "EQUIPEMENT CONTRAT 38"-------------------------
//             if ($contentAllLists[$key]['class'] === 'PORTAILS') {
//                 dump("Liste des IDs des listes portails par agence");
//                 dump($contentAllLists[$key]['id']);
//                 $response = $this->client->request(
//                     'GET',
//                     'https://forms.kizeo.com/rest/v3/lists/' . $contentAllLists[$key]['id'], [
//                         'headers' => [
//                             'Accept' => 'application/json',
//                             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
//                         ],
//                     ]
//                 );
                
//                 $content = $response->getContent();
//                 $content = $response->toArray();
                
                
//                 $portailsArray = array_map(null, $content['list']['items']);
//                 for ($i=0; $i < count($portailsArray) ; $i++) {
//                     if (isset($portailsArray[$i])) {
//                         array_push($portailsSplittedArray, array_unique(preg_split("/[:|]/", $portailsArray[$i])));
//                     }
//                 }
//             }
//         }
//         return $portailsSplittedArray;
//    }
}
