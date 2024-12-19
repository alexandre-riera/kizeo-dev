<?php

namespace App\Repository;

use App\Entity\ContactS40;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactS40Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  private HttpClientInterface $client)
    {
        parent::__construct($registry, ContactS40::class);
    }

    // /**
    //  * @return Returns an array of Contact objects splitted by : and |
    //  */
    // public function splitString($contentArray){
    //     $contentArraySplitted = preg_split("/[:|]/", $contentArray);

    //     return $contentArraySplitted;
    // }

   /**
    * @return ContactS40[] Returns an array of Contact objects
    */
   public function getContacts(): array
   {
        $contactsArray = [];
        $contactsSplittedArray = [];

        // Request Liste clients 42
        $response = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/lists/409466', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
            ]
            )
        ;
        $content = $response->getContent();
        $content = $response->toArray();
        
        
        $contactsArray = array_map(null, $content['list']['items']);
        for ($i=0; $i < count($contactsArray) ; $i++) {
            if (isset($contactsArray[$i])) {
                array_push($contactsSplittedArray, array_unique(preg_split("/[:|]/", $contactsArray[$i])));
            }
        }
        return $contactsSplittedArray;
   }
}
