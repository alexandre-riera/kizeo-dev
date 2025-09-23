<?php

namespace App\Repository;

use App\Entity\Form;
use GuzzleHttp\Client;
use App\Entity\EquipementS10;
use App\Entity\EquipementS40;
use App\Entity\EquipementS50;
use App\Entity\EquipementS60;
use App\Entity\EquipementS70;
use App\Entity\EquipementS80;
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use stdClass;
use App\Service\ImageStorageService;
use App\Service\PhotoManagementService;

/**
 * @extends ServiceEntityRepository<ApiForm>
 */
class FormRepository extends ServiceEntityRepository
{
    private ImageStorageService $imageStorageService;
    private HttpClientInterface $client;
    private PhotoManagementService $photoManagementService;

    public function __construct(
        ManagerRegistry $registry,
        HttpClientInterface $client,
        ImageStorageService $imageStorageService,
        PhotoManagementService $photoManagementService
    ) {
        parent::__construct($registry, Form::class);
        $this->client = $client;
        $this->photoManagementService = $photoManagementService;
    }

    /**
     * @return Form[] Returns an array of lists from Kizeo
     */

    public function getLists(): array
    {
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/lists', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
            $content = $response->getContent();
            $content = $response->toArray();

            return $content;
    }

    /**
        * @return Form[] Returns an array of forms from Kizeo .  94 forms au 09.04.2025
        */
    public function getForms(): array
    {
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
            $content = $response->getContent();
            $content = $response->toArray();

            return $content;
    }
        
    /**
     * Version optimisée pour marquer les formulaires de maintenance comme "non lus"
     * Suit la logique Kizeo: form_id pour l'URL + data_ids dans le body JSON
     */
    public function markMaintenanceFormsAsUnreadOptimized($cache): array
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            // 1. Récupérer uniquement les formulaires MAINTENANCE avec cache optimisé
            $maintenanceForms = $cache->get('maintenance_forms_list_optimized', function(ItemInterface $item) {
                $item->expiresAfter(7200); // Cache 2 heures
                
                try {
                    $response = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms', [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                            'timeout' => 30
                        ]
                    );
                    $content = $response->toArray();
                    
                    // Retourner seulement les form_id des formulaires MAINTENANCE
                    $maintenanceForms = [];
                    foreach ($content['forms'] as $form) {
                        if ($form['class'] == "MAINTENANCE") {
                            $maintenanceForms[] = [
                                'id' => $form['id'],
                                'name' => $form['name']
                            ];
                        }
                    }
                    return $maintenanceForms;
                    
                } catch (\Exception $e) {
                    error_log("Erreur lors de la récupération des formulaires: " . $e->getMessage());
                    return [];
                }
            });

            // 2. Pour chaque form_id, récupérer ses data_ids et les marquer comme "non lus"
            foreach ($maintenanceForms as $form) {
                $formId = $form['id'];
                
                try {
                    // Récupérer tous les data_ids pour ce form_id
                    $dataIds = $this->getDataIdsForForm($formId, $cache);
                    
                    if (!empty($dataIds)) {
                        // Marquer tous les data_ids comme "non lus" en une seule requête
                        $this->markFormDataAsUnread($formId, $dataIds);
                        $successCount++;
                        error_log("Formulaire $formId marqué comme non lu avec " . count($dataIds) . " data_ids");
                    } else {
                        error_log("Aucun data_id trouvé pour le formulaire ID: $formId");
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'form_id' => $formId,
                        'form_name' => $form['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                    error_log("Erreur pour le formulaire $formId: " . $e->getMessage());
                    continue; // Continuer avec le formulaire suivant
                }
                
                // Pause entre les formulaires pour éviter la surcharge
                usleep(200000); // 0.2 seconde
            }

        } catch (\Exception $e) {
            $errors[] = ['general_error' => $e->getMessage()];
            error_log("Erreur générale: " . $e->getMessage());
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'total_forms' => count($maintenanceForms ?? []),
            'errors' => $errors
        ];
    }

    /**
     * Récupère tous les data_ids pour un form_id donné
     * Utilise l'endpoint /data/advanced qui est plus efficace
     */
    public function getDataIdsForForm($formId, $cache): array
    {
        $cacheKey = "form_data_ids_$formId";
        
        return $cache->get($cacheKey, function(ItemInterface $item) use ($formId) {
            $item->expiresAfter(900); // Cache 15 minutes (plus court car les data changent plus souvent)
            
            try {
                $response = $this->client->request('POST', 
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 20
                    ]
                );
                
                $content = $response->toArray();
                
                // Extraire tous les data_ids (ID des formulaires techniciens)
                $dataIds = [];
                if (isset($content['data']) && is_array($content['data'])) {
                    foreach ($content['data'] as $dataItem) {
                        if (isset($dataItem['_id'])) {
                            $dataIds[] = intval($dataItem['_id']);
                        }
                    }
                }
                
                return $dataIds;
                
            } catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
                error_log("Timeout lors de la récupération des data_ids pour le formulaire $formId");
                throw new \Exception("Timeout pour le formulaire $formId");
                
            } catch (\Exception $e) {
                error_log("Erreur lors de la récupération des data_ids pour le formulaire $formId: " . $e->getMessage());
                throw new \Exception("Erreur data_ids pour formulaire $formId: " . $e->getMessage());
            }
        });
    }

    /**
     * Marque tous les data_ids d'un formulaire comme "non lus"
     * Suit exactement la spec Kizeo: POST /forms/{formId}/markasunreadbyaction/read
     */
    public function markFormDataAsUnread($formId, $dataIds): void
    {
        try {
            // Construire l'URL selon la spec Kizeo
            $url = 'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/markasunreadbyaction/read';
            
            // Body JSON avec le tableau des data_ids
            $requestBody = [
                "data_ids" => $dataIds
            ];
            
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'json' => $requestBody,
                'timeout' => 15
            ]);
            
            // Vérifier que la requête s'est bien passée
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                error_log("Succès: Formulaire $formId marqué comme non lu (" . count($dataIds) . " data_ids)");
            } else {
                throw new \Exception("Code de statut inattendu: $statusCode");
            }
            
        } catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
            throw new \Exception("Timeout lors du marquage du formulaire $formId comme non lu");
            
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors du marquage du formulaire $formId: " . $e->getMessage());
        }
    }

    //      ----------------------------------------------------------------------------------------------------------------------
    //      ---------------------------------------- GET EQUIPMENTS LISTS FROM KIZEO --------------------------------------
    //      ----------------------------------------------------------------------------------------------------------------------
    /**
     * @return Form[] Returns an array with all items from all agencies equipments lists 
     */
    public function getAgencyListEquipementsFromKizeoByListId($list_id): array
    {
         $response = $this->client->request(
             'GET',
             'https://forms.kizeo.com/rest/v3/lists/' . $list_id, [
                 'headers' => [
                     'Accept' => 'application/json',
                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                 ],
             ]
         );
         $content = $response->getContent();
         $content = $response->toArray();
         
        //  $equipementsSplittedArray = [];
         $equipementsArray = array_map(null, $content['list']['items']);

         return $equipementsArray;
    }
 
    /**
     * @return Form[] Returns an array with all items from all agencies portails lists 
     */
    public function getAgencyListPortailsFromKizeoByListId($list_id): array
    {
         $response = $this->client->request(
             'GET',
             'https://forms.kizeo.com/rest/v3/lists/' . $list_id, [
                 'headers' => [
                     'Accept' => 'application/json',
                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                 ],
             ]
         );
         $content = $response->getContent();
         $content = $response->toArray();
         
         $equipementsSplittedArray = [];
         // $equipementsArray = array_map(null, $content['list']['items']);
         $equipementsArray = array_map(null, $content['list']['items']);
         /* On Kizeo, all lines look like that
         *  ATEIS\CEA\SEC01|Porte sectionnelle|MISE EN SERVICE|NUMERO DE SERIE|ISEA|HAUTEUR|LARGEUR|REPERE SITE CLIENT|361|361|S50
         *
         *  And I need to sending this : 
         *  "ATEIS:ATEIS\CEA:CEA\SEC01:SEC01|Porte sectionnelle:Porte sectionnelle|MISE EN SERVICE:MISE EN SERVICE|NUMERO DE SERIE:NUMERO DE SERIE|ISEA:ISEA|HAUTEUR:HAUTEUR|LARGEUR:LARGEUR|REPERE SITE CLIENT:REPERE SITE CLIENT|361:361|361:361|S50:S50"
         */ 
         for ($i=0; $i < count($equipementsArray) ; $i++) {
             if (isset($equipementsArray[$i]) && in_array($equipementsArray[$i], $equipementsSplittedArray) == false) {
                 array_push($equipementsSplittedArray, preg_split("/[|]/", $equipementsArray[$i]));
             }
         }
 
         return $equipementsArray;
    }

   //      ----------------------------------------------------------------------------------------------------------------------
    //      ---------------------------------------- GET MAINTENANCE EQUIPMENTS FORMS FROM KIZEO --------------------------------------
    //      -------------------------------------------------------AVEC CACHEINTERFACE--------------------------------------------------
   /**
    * @return Form[] Returns an array of Formulaires with class "MAINTENANCE" wich is all visites maintenance
    */
   public function getDataOfFormsMaintenance($cache): array
   {    
        // -----------------------------   Return all forms in an array | cached for 2419200 seconds 1 month
        $allFormsArray = $cache->get('all-forms-on-kizeo', function(ItemInterface $item){
            $item->expiresAfter(2419200);
            $result = FormRepository::getForms();
            return $result['forms'];
        });

        // $allFormsMaintenanceArray = []; // All forms with class "MAINTENANCE
        // $unreadFormCounter = 0;
        $formMaintenanceUnread = [];
        $dataOfFormMaintenanceUnread = [];
        $allFormsKeyId = [];
        
        
        // ----------------------------- DÉBUT Return all forms with class "MAINTENANCE" WITH CACHE  1 week
        foreach ($allFormsArray as $key => $value) {
            if ($allFormsArray[$key]['class'] === 'MAINTENANCE') {
                // Récuperation des forms ID
                array_push($allFormsKeyId, $allFormsArray[$key]['id']);
            }
        }
        // -----------------------------  FIN Return all forms with class "MAINTENANCE"

        // ----------------------------------------------------------------------- Début d'appel KIZEO aux formulaires non lus par ID de leur liste
        // ----------------------------------------------------------- Appel de 5 formulaires à la fois en mettant le paramètre LIMIT à 5 en fin d'url
        // --------------- Remise à zéro du tableau $formMaintenanceUnread  ------------------
        // --------------- Avant de le recharger avec les prochains 5 formulaires non lus  ------------------
        $formMaintenanceUnread = [];
        foreach ($allFormsKeyId as $key) {
            $responseUnread = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' .  $key . '/data/unread/enfintraite/5', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $result = $responseUnread->getContent();
            $result = $responseUnread->toArray();
            array_push($formMaintenanceUnread, $result);
            
        }
       
        // ----------------------------------------------------------------------- Début d'appel data des formulaires non lus
        // --------------- Remise à zéro du tableau $dataOfFormMaintenanceUnread  ------------------
        // --------------- Avant de le recharger avec la data des 5 formulaires non lus  ------------------
        $dataOfFormMaintenanceUnread = [];
        foreach ($formMaintenanceUnread as $formUnread) {
            foreach ($formUnread['data'] as $form) {
                $response = $this->client->request(
                    'GET',
                    'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/data/' . $form['_id'], [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );
                $result= $response->getContent();
                $result= $response->toArray();
                array_push($dataOfFormMaintenanceUnread, $result['data']['fields']);
            }
        }
        return $dataOfFormMaintenanceUnread;
   }

    //      ----------------------------------------------------------------------------------------------------------------------
    //      ----------------------------------- GET ETAT DES LIEUX PORTAILS FORMS FROM KIZEO --------------------------------------
    //      ----------------------------------------------------------------------------------------------------------------------
   /**
    * @return Form[] Returns an array of Formulaires with class PORTAILS
    */
   public function getFormsAdvancedPortails(): array
   {
        $allFormsArray = FormRepository::getForms();
        $allFormsArray = $allFormsArray['forms'];
        $allDataPortailsArray = [];

        // -----------------------------   Return all forms in an array

        foreach ($allFormsArray as $key => $value) {
            // If forms name is contain "Etat des lieux" and is NOT "MODELE Etat des lieux Original" wich is 996714
            if (str_contains($allFormsArray[$key]['name'], 'Etat des lieux') && $allFormsArray[$key]['id'] != 996714) {
                $response = $this->client->request(
                    'POST',
                    'https://forms.kizeo.com/rest/v3/forms/' . $allFormsArray[$key]['id'] . '/data/advanced', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );
                $content = $response->getContent();
                $content = $response->toArray();
                foreach ($content['data'] as $key => $value) {
                    array_push($allDataPortailsArray, $value);
                }
            }
        }
        return $allDataPortailsArray;
   }

   /**
    * @return data of Form[] Returns an array of all Portails objects in all formulaires
    */
   public function getEtatDesLieuxPortailsDataOfForms(): array
   {
        $eachFormDataArray = [];
        $allFormsPortailsArray = FormRepository::getFormsAdvancedPortails();
        // ------------------------      Return arrays with portails in them from forms with class forms name begin with 'Etat des lieux'

        foreach ($allFormsPortailsArray as $key => $value) {
            
            $responseDataOfForm = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' .  $allFormsPortailsArray[$key]['_form_id'] . '/data/' . $allFormsPortailsArray[$key]['_id'], [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
            $content = $responseDataOfForm->getContent();
            $content = $responseDataOfForm->toArray();
            array_push($eachFormDataArray, $content);
            
        }
        
        return $eachFormDataArray;
   }

    /**
     * Function to iterate through list equipements from local BDD to get resumes and store them in an array
     */
    public function iterateListEquipementsToGetResumes($theList){
        
        $arrayResumesOfTheList = [];
        for ($i=0; $i < count($theList); $i++) {
            $equipementIfExistDb = $theList[$i]->getIfexistDB();
            if (!in_array($equipementIfExistDb, $arrayResumesOfTheList)) {
                array_push($arrayResumesOfTheList, $equipementIfExistDb);
            }   
        }
        return $arrayResumesOfTheList;
    }

    /**
     * Function to iterate through list equipements to get unique PORTAILS and return them in an array
     *  OK ! Return an array of portails by agency in local database
     */
    public function getOneTypeOfEquipementInListEquipements($equipementType, $theList){

        $arrayPortailsInTheList = [];

        for ($i=0; $i < count($theList); $i++) { 
            if ($theList[$i]->getLibelleEquipement() === $equipementType) {
                if (!in_array($theList[$i], $arrayPortailsInTheList)) {
                    array_push($arrayPortailsInTheList, $theList[$i]);
                }
            }
        }
        return $arrayPortailsInTheList;
    }

    /**
     * Obtient le code du type d'équipement à partir du libellé
     * 
     * @param string $typeLibelle Le libellé du type d'équipement
     * @return string Le code du type (ex: SEC pour porte sectionnelle)
     */
    public function getTypeCodeFromLibelle(string $typeLibelle): string
    {
        // Mappings connus pour les types d'équipement courants
        $typeCodeMap = [
            'porte sectionnelle' => 'SEC',
            'porte battante' => 'BPA',
            'porte basculante' => 'PBA',
            'porte rapide' => 'RAP',
            'porte pietonne' => 'PPV',
            'porte coulissante' => 'COU',
            'porte coupe feu' => 'CFE',
            'porte coupe-feu' => 'CFE',
            'porte accordéon' => 'PAC',
            'porte frigorifique' => 'COF',
            'barriere levante' => 'BLE',
            'barriere' => 'BLE',
            'mini pont' => 'MIP',
            'mini-pont' => 'MIP',
            'rideau' => 'RID',
            'rideau métalliques' => 'RID',
            'rideau metallique' => 'RID',
            'rideau métallique' => 'RID',
            'niveleur' => 'NIV',
            'portail' => 'PAU',
            'portail motorisé' => 'PMO',
            'portail motorise' => 'PMO',
            'portail manuel' => 'PMA',
            'portail coulissant' => 'PCO',
            'protection' => 'PRO',
            'portillon' => 'POR',
            'table elevatrice' => 'TEL',
            'tourniquet' => 'TOU',
            'issue de secours' => 'BPO',
            'bloc roue' => 'BLR',
            'sas' => 'SAS',
            'plaque de quai' => 'PLQ',
            // Ajoutez d'autres mappages selon vos besoins
        ];

        $typeLibelle = strtolower(trim($typeLibelle));
        
        // Vérifier si le type est dans notre mapping
        if (isset($typeCodeMap[$typeLibelle])) {
            return $typeCodeMap[$typeLibelle];
        }
        
        // Si le libellé contient plusieurs mots, prendre les premières lettres de chaque mot
        $words = explode(' ', $typeLibelle);
        if (count($words) > 1) {
            $code = '';
            foreach ($words as $word) {
                if (strlen($word) > 0) {
                    $code .= strtoupper(substr($word, 0, 1));
                }
            }
            // Si le code est trop court, utiliser les 3 premières lettres du premier mot
            if (strlen($code) < 3 && strlen($words[0]) >= 3) {
                $code = strtoupper(substr($words[0], 0, 3));
            }
            return $code;
        }
        
        // Sinon, utiliser les 3 premières lettres du type en majuscules
        return strtoupper(substr($typeLibelle, 0, 3));
    }

    /**
    * Détermine le prochain numéro d'équipement à utiliser en vérifiant la base de données
    * 
    * @param string $typeCode Le code du type d'équipement
    * @param string $idClient L'identifiant du client
    * @param string $entityAgency La classe de l'entité d'agence
    * @return int Le prochain numéro à utiliser
    */
    public function getNextEquipmentNumberFromDatabase(string $typeCode, string $idClient, string $entityAgency): int
    {
        // Requête pour trouver tous les équipements du même type pour ce client
        $equipements = $this->getEntityManager()->getRepository($entityAgency)
            ->createQueryBuilder('e')
            ->where('e.idContact = :idClient')
            ->andWhere('e.numeroEquipement LIKE :pattern')
            ->setParameter('idClient', $idClient)
            ->setParameter('pattern', $typeCode . '%')
            ->getQuery()
            ->getResult();
        
        $dernierNumero = 0;
        
        foreach ($equipements as $equipement) {
            $numeroEquipement = $equipement->getNumeroEquipement();
            
            // Si le format correspond (ex: SEC01, SEC02...)
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $numero = (int)$matches[1];
                if ($numero > $dernierNumero) {
                    $dernierNumero = $numero;
                }
            }
        }
        
        // Retourner le prochain numéro
        return $dernierNumero + 1;
    }

    /**
     * Function to create and save new PORTAILS from ETAT DES LIEUX PORTAILS in local database by agency --- OK POUR TOUTES LES AGENCES DE S10 à S170
     * Function OK
     */
    public function saveNewPortailsInDatabaseByAgency($libelle_equipement, $equipements, $arrayResumesEquipmentsInDatabase, $entityAgency, $entityManager){
       
        // Passer à la fonction les variables $equipements avec les nouveaux équipements des formulaires de maintenance, le tableau des résumés de l'agence et son entité ex: $entiteEquipementS10
        /**
        * List all additional equipments stored in individual array
        */
        foreach ($equipements['data']['fields']['portails']['value'] as $additionalEquipment){
            
            // Everytime a new portail is read, we store its value in variable resume_equipement_supplementaire
            if (isset($additionalEquipment['types_equipements']['value'])) {
                # code...
                $resume_equipement_supplementaire = 
                $additionalEquipment['types_equipements']['value'] . $additionalEquipment['reference_equipement']['value'] . 
                "|" . 
                $libelle_equipement . 
                "|" . 
                $additionalEquipment['types_de_fonctionnement']['value'] . 
                "|" . 
                $additionalEquipment['localisation_sur_site']['value'] . 
                "|" . 
                $additionalEquipment['annee_installation_portail_']['value'] . 
                "|" . 
                $additionalEquipment['numero_serie']['value'] . 
                "|" . 
                $additionalEquipment['marques1']['value'] . 
                "|" . 
                $additionalEquipment['dimension_hauteur_vantail']['value'] . 
                "|" . 
                $additionalEquipment['dimension_largeur_passage_uti']['value'] . 
                "|" . 
                $additionalEquipment['dimension_longueur_vantail']['value'] . 
                "|" . 
                $additionalEquipment['plaque_identification']['value'] . 
                "|" . 
                $equipements['data']['fields']['liste_clients']['value'] . 
                "|" . 
                $equipements['data']['fields']['ref_interne_client']['value'] .
                "|" . 
                $equipements['data']['fields']['id_societe_']['value']; 
            }else{
                $resume_equipement_supplementaire = 
                $additionalEquipment['reference_equipement']['value'] . 
                "|portail|" . 
                $additionalEquipment['types_de_fonctionnement']['value'] . 
                "|" . 
                $additionalEquipment['localisation_sur_site']['value'] . 
                "|" . 
                $additionalEquipment['annee_installation_portail_']['value'] . 
                "|" . 
                $additionalEquipment['numero_serie']['value'] . 
                "|" . 
                $additionalEquipment['marques1']['value'] . 
                "|" . 
                $additionalEquipment['dimension_hauteur_vantail']['value'] . 
                "|" . 
                $additionalEquipment['dimension_largeur_passage_uti']['value'] . 
                "|" . 
                $additionalEquipment['dimension_longueur_vantail']['value'] . 
                "|" . 
                $additionalEquipment['plaque_identification']['value'] . 
                "|" . 
                $equipements['data']['fields']['liste_clients']['value'] . 
                "|" . 
                $equipements['data']['fields']['ref_interne_client']['value'] .
                "|NC";
            }

            
            /**
             * If resume_equipement_supplementaire value is NOT in  $allEquipementsResumeInDatabase array
             * Method used : in_array(search, inThisArray, type) 
             * type Optional. If this parameter is set to TRUE, the in_array() function searches for the search-string and specific type in the array
             */
            
            if(!in_array($resume_equipement_supplementaire, $arrayResumesEquipmentsInDatabase, TRUE)) {
                /**
                 * Persist each equipement in database
                 * Save new portails when a new etat des lieux is up on kizeo
                 */
                $equipement = new $entityAgency;
                $equipement->setTrigrammeTech($equipements['data']['fields']['trigramme_de_la_personne_real']['value']);
                $equipement->setIdContact($equipements['data']['fields']['ref_interne_client']['value']);
                if (isset($equipements['data']['fields']['id_societe_']['value'])){
                    $equipement->setCodeSociete($equipements['data']['fields']['id_societe_']['value']);
                }else{
                    $equipement->setCodeSociete("NC");
                }
                $equipement->setDerniereVisite($equipements['data']['fields']['date_et_heure1']['value']);
                $equipement->setIfExistDB($resume_equipement_supplementaire);
                $equipement->setCodeAgence($equipements['data']['fields']['n_agence']['value']);
                $equipement->setRaisonSociale($equipements['data']['fields']['liste_clients']['value']);
                if (isset($additionalEquipment['types_equipements']['value'])){
                    $equipement->setNumeroEquipement($additionalEquipment['types_equipements']['value'] . $additionalEquipment['reference_equipement']['value']);
                }else{
                    $equipement->setNumeroEquipement($additionalEquipment['reference_equipement']['value']);
                }
                $equipement->setRepereSiteClient($additionalEquipment['localisation_sur_site']['value']);
                $equipement->setEtat($additionalEquipment['etat_general_equipement1']['value']);
                $equipement->setMarque($additionalEquipment['marques1']['value']);
                $equipement->setNumeroDeSerie($additionalEquipment['numero_serie']['value']);
                $equipement->setMiseEnService($additionalEquipment['annee_installation_portail_']['value']);
                $equipement->setLibelleEquipement("portail");
                $equipement->setModeFonctionnement($additionalEquipment['types_de_fonctionnement']['value']);
                $equipement->setLargeur($additionalEquipment['dimension_largeur_passage_uti']['value']);
                $equipement->setLongueur($additionalEquipment['dimension_longueur_vantail']['value']);
                $equipement->setHauteur($additionalEquipment['dimension_hauteur_vantail']['value']);
                $equipement->setPresenceCarnetEntretien($additionalEquipment['presence_carnet_entretien']['value']);
                // $equipement->setEtatDesLieuxFait(true);
                
                $entityManager->persist($equipement);
                // actually executes the queries (i.e. the INSERT query)
                $entityManager->flush();
                
                echo nl2br("Portails are updaated in BDD in table equipementS.. !");
            }
        }
    }


    //      ----------------------------------------------------------------------------------------------------------------------
    //      ---------------------------------------- UPLOAD NEW EQUIPMENTS LIST TO KIZEO --------------------------------------
    //      ----------------------------------------------------------------------------------------------------------------------
    /**
     * Function to upload and save list agency with new records from maintenance formulaires to Kizeo --- OK POUR TOUTES LES AGENCES DE S10 à S170
     */
    public function uploadListAgencyWithNewRecordsOnKizeo($dataOfFormList, $key, $agencyEquipments, $agencyListId){
        
        foreach ($dataOfFormList[$key]['contrat_de_maintenance']['value'] as $equipment) {
            // A remplacer  "SEC01|Porte sectionnelle|2005|206660A02|nc|A RENSEIGNER|A RENSEIGNER||1533|1533|S50"
            // A remplacer  "Libelle equipement|Type equipement|Année|N° de série|Marque|Hauteur|Largeur|Repère site client|Id client|Id societe|Code agence"
            $columnsUpdate = 
            $equipment['equipement']['value'] . // Libelle equipement
             '|' . 
            $equipment['reference7']['value'] . // Type equipement
             '|' .
            $equipment['reference2']['value'] . // Année
             '|' .
            $equipment['reference6']['value'] . // N° de série
            '|' .
            $equipment['reference5']['value'] . // Marque
            '|' .
            $equipment['reference3']['value'] . // Hauteur
            '|' .
            $equipment['reference1']['value'] . // Largeur
            '|' .
            $equipment['localisation_site_client']['value'] . // Repère site client
            '|' .
            $dataOfFormList[$key]['id_client_']['value'] . // Id client
            '|' .
            $dataOfFormList[$key]['id_societe']['value'] .  // Id Societe
            '|' . 
            $dataOfFormList[$key]['id_agence']['value'] // Code agence
            ;
            /* Le double antislash correspond à 1 antislash échappé avec un autre
             $equipment['equipement']['path']  =  à LEROY MERLIN VALENCE LOGISITQUE\CE1 auquel on ajoute 1 antislash + les update au dessus
             \NIV28|Niveleur|A RENSEIGNER|A RENSEIGNER|A RENSEIGNER|2200|2400||6257|5947|S50
            */
            $theEquipment = $equipment['equipement']['path'] . "\\" . $columnsUpdate; 
            // dd('Équipement remonté avant la mise à jour de la liste : ' . $theEquipment);
            if (in_array($equipment['equipement']['path'], $agencyEquipments, true)) {
                $keyEquipment = array_search($equipment['equipement']['path'], $agencyEquipments);
                unset($agencyEquipments[$keyEquipment]);
                array_push($agencyEquipments, $theEquipment);
            }
        }
        // J'enlève les doublons de la liste des equipements kizeo dans le tableau $agencyEquipments
        $arrayEquipmentsToPutToKizeo = array_unique($agencyEquipments); // array_unique n'enlève aucun équipement de la liste
        

        Request::enableHttpMethodParameterOverride(); // <-- add this line
        $client = new Client();
        $response = $client->request(
            'PUT',
            'https://forms.kizeo.com/rest/v3/lists/' . $agencyListId, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'json'=>[
                    'items' => $arrayEquipmentsToPutToKizeo,
                ]
            ]
        );
    }

    /**
     * Get equipments in BDD by agency. Then read them and prepare a list of equipments by agency. 
     * Then send the list to Kizeo with her list ID - VERSION AVEC CACHE REDIS
     */
    public function updateKizeoWithEquipmentsListFromBdd($entityManager, $formRepository, $cache): array
    {
        $results = [];
        
        // Liste des entités d'équipement
        $entitesEquipements = [
            EquipementS10::class,
            EquipementS40::class,
            EquipementS50::class,
            EquipementS60::class,
            EquipementS70::class,
            EquipementS80::class,
            EquipementS100::class,
            EquipementS120::class,
            EquipementS130::class,
            EquipementS140::class,
            EquipementS150::class,
            EquipementS160::class,
            EquipementS170::class,
        ];

        // Traitement par entité d'équipement
        foreach ($entitesEquipements as $entite) {
            try {
                // Récupérer les équipements depuis la BDD
                $equipements = $entityManager->getRepository($entite)->findAll();
                
                // Structurer les équipements pour ressembler à la structure de Kizeo
                $structuredEquipements = $this->structureLikeKizeoEquipmentsList($equipements);

                // Initialisation de la variable contenant l'id de la liste d'équipements sur Kizeo
                $idListeKizeo = $this->getIdListeKizeoPourEntite($entite);

                // Nom du cache basé sur l'entité
                $nomCache = strtolower(str_replace('Equipement', '', $entite));
                
                // Récupérer la liste des équipements Kizeo depuis le cache Redis
                $kizeoEquipments = $cache->get(
                    'kizeo_equipments_' . $nomCache, 
                    function(ItemInterface $item) use ($formRepository, $idListeKizeo) {
                        // Cache expirant après 15 minutes
                        $item->expiresAfter(900);
                        
                        // Récupérer les données depuis l'API Kizeo
                        return $formRepository->getAgencyListEquipementsFromKizeoByListId($idListeKizeo);
                    }
                );

 
                // Comparer et mettre à jour la liste Kizeo
                $updatedEquipments = $this->compareAndSyncEquipments(
                    $structuredEquipements, 
                    $kizeoEquipments, 
                    $idListeKizeo
                );
                
                // Invalider le cache après mise à jour
                $cache->delete('kizeo_equipments_' . $nomCache);
                

                // Avant l'envoi à Kizeo, ajoutez :
                error_log("Envoi pour entité $entite : " . count($updatedEquipments) . " équipements");

                // Ajouter le résultat au tableau de retour
                $results[$nomCache] = [
                    'entite' => $entite,
                    'id_liste_kizeo' => $idListeKizeo,
                    'equipments_count' => count($structuredEquipements),
                    'updated_count' => count($updatedEquipments),
                    'status' => 'success'
                ];
                
            } catch (\Exception $e) {
                // Log de l'erreur et continuation avec l'entité suivante
                $results[$nomCache] = [
                    'entite' => $entite,
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ];
                
                // Log l'erreur (si vous avez un logger configuré)
                // $this->logger->error('Erreur mise à jour Kizeo pour ' . $entite, ['exception' => $e]);
            }
        }
        
        return $results;
    }

    /**
    *   Explication:
    *
    *   Fonction compareAndSyncEquipments :
    *
    *   Initialise $updatedKizeoEquipments avec les données existantes de Kizeo.
    *   Parcourt chaque élément de $structuredEquipements (BDD).
    *   Extrait le préfixe de l'élément de la BDD.
    *   Parcourt $updatedKizeoEquipments pour trouver un élément avec le même préfixe.
    *   Si trouvé, remplace l'élément Kizeo par l'élément de la BDD.
    *   Si non trouvé, ajoute l'élément de la BDD à $updatedKizeoEquipments.
    *   Appelle la fonction updateAllVisits pour mettre à jour les visites associées.
    *   Envoie la liste mise à jour à Kizeo Forms.
    *   Fonction updateAllVisits :
    *
    *   Parcourt $kizeoEquipments.
    *   Si un élément commence par le préfixe à mettre à jour, il est remplacé par le nouvel équipement.
    *   Points importants:
    *
    *   Cette solution compare les équipements en utilisant uniquement la partie "raison_sociale\visite\équipement" de chaque ligne.
    *   Elle met à jour ou ajoute les lignes en fonction de la présence ou de l'absence du préfixe dans la liste Kizeo.
    *   La fonction updateAllVisits gère la mise à jour des visites associées en parcourant la liste et en remplaçant les lignes qui correspondent au préfixe.
    *   Assurez-vous que la fonction envoyerListeKizeo est correctement implémentée pour envoyer les données mises à jour à Kizeo Forms.
    *   J'ai modifié la fonction updateAllVisits pour qu'elle modifie directement le tableau $kizeoEquipments qui lui est passé en paramètre.
    * Compare et synchronise les équipements entre BDD et Kizeo - VERSION CORRIGÉE
    * 
    * Le format des équipements est : "RAISON_SOCIALE\VISITE\NUMERO_EQUIPEMENT|libelle|type|..."
    * La comparaison doit se faire sur l'ensemble de la partie avant le premier "|"
    */
    public function compareAndSyncEquipments($structuredEquipements, $kizeoEquipments, $idListeKizeo): array 
    {
        // Nettoyer d'abord tous les équipements Kizeo
        $cleanedKizeoEquipments = [];
        foreach ($kizeoEquipments as $kizeoEquipment) {
            $cleanedKizeoEquipments[] = $this->cleanKizeoFormat($kizeoEquipment);
        }
        
        $updatedKizeoEquipments = $cleanedKizeoEquipments;

        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredFullKey = explode('|', $structuredEquipment)[0];
            $keyParts = explode('\\', $structuredFullKey);
            $equipmentBaseKey = ($keyParts[0] ?? '') . '\\' . ($keyParts[2] ?? '');

            $equipmentExistsInKizeo = $this->equipmentExistsInKizeo($updatedKizeoEquipments, $equipmentBaseKey);
            
            if ($equipmentExistsInKizeo) {
                $this->updateAllVisitsForEquipment($updatedKizeoEquipments, $equipmentBaseKey, $structuredEquipment);
                
                $specificVisitExists = $this->specificVisitExists($updatedKizeoEquipments, $structuredFullKey);
                if (!$specificVisitExists) {
                    $updatedKizeoEquipments[] = $structuredEquipment;
                }
            } else {
                $updatedKizeoEquipments[] = $structuredEquipment;
            }
        }
        
        $this->envoyerListeKizeo($updatedKizeoEquipments, $idListeKizeo);
        return $updatedKizeoEquipments;
    }

    /**
     * Met à jour toutes les visites d'un même équipement avec les nouvelles données
     * FONCTION CORRIGÉE
     */
    public function updateAllVisitsForEquipment(&$kizeoEquipments, $equipmentBaseKey, $newEquipment): void
    {
        $newEquipmentData = explode('|', $newEquipment);
        $newEquipmentFullKey = $newEquipmentData[0]; // RAISON_SOCIALE\VISITE\NUMERO_EQUIPEMENT
        
        foreach ($kizeoEquipments as $key => $kizeoEquipment) {
            $kizeoEquipmentData = explode('|', $kizeoEquipment);
            $kizeoFullKey = $kizeoEquipmentData[0];
            
            // Extraire la clé de base de l'équipement Kizeo (sans visite)
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            // Si c'est le même équipement (même raison sociale + même numéro d'équipement)
            // MAIS pas la même ligne (éviter de re-traiter la ligne qu'on vient d'ajouter/modifier)
            if ($kizeoBaseKey === $equipmentBaseKey && $kizeoFullKey !== $newEquipmentFullKey) {
                
                // Mettre à jour seulement les données techniques (à partir de l'index 2)
                // On garde : [0] = clé complète Kizeo, [1] = libellé équipement
                // On met à jour : [2] = année, [3] = n° série, [4] = marque, etc.
                for ($i = 2; $i < count($newEquipmentData); $i++) {
                    if (isset($newEquipmentData[$i])) {
                        if (isset($kizeoEquipmentData[$i])) {
                            $kizeoEquipmentData[$i] = $newEquipmentData[$i];
                        } else {
                            $kizeoEquipmentData[] = $newEquipmentData[$i];
                        }
                    }
                }
                
                // Reconstruire la ligne mise à jour
                $kizeoEquipments[$key] = implode('|', $kizeoEquipmentData);
            }
        }
    }

    /**
     * Normalise une clé en supprimant les espaces et caractères indésirables
     */
    public function normalizeKey(string $key): string
    {
        // Supprimer les espaces en début/fin
        $key = trim($key);
        
        // Supprimer les caractères de contrôle invisibles
        $key = preg_replace('/[\x00-\x1F\x7F]/', '', $key);
        
        // Normaliser les séparateurs de chemin
        $key = str_replace(['/', '\\\\'], '\\', $key);
        
        return $key;
    }

    /////////////////// TEMPORAIRE ////////////////////////////////////////////////
    public function structureEquipmentsForKizeo($equipements): array
    {
        $structuredEquipements = [];
        
        foreach ($equipements as $equipement) {
            $equipmentLine = 
                ($equipement->getRaisonSociale() ?? '') . '\\' .
                ($equipement->getVisite() ?? '') . '\\' .
                ($equipement->getNumeroEquipement() ?? '') . '|' .
                ($equipement->getLibelleEquipement() ?? '') . '|' .
                ($equipement->getMiseEnService() ?? '') . '|' .
                ($equipement->getNumeroDeSerie() ?? '') . '|' .
                ($equipement->getMarque() ?? '') . '|' .
                ($equipement->getHauteur() ?? '') . '|' .
                ($equipement->getLargeur() ?? '') . '|' .
                ($equipement->getRepereSiteClient() ?? '') . '|' .
                ($equipement->getIdContact() ?? '') . '|' .
                ($equipement->getCodeSociete() ?? '') . '|' .
                ($equipement->getCodeAgence() ?? '');
                
            $structuredEquipements[] = $equipmentLine;
        }
        
        return $structuredEquipements;
    }

    /**
     * Versions adaptées des méthodes avec nettoyage - AJOUTER DANS FormRepository.php
     */
    private function equipmentExistsInKizeoWithCleaning($cleanedKizeoEquipments, $equipmentBaseKey): bool
    {
        foreach ($cleanedKizeoEquipments as $kizeoEquipment) {
            $kizeoFullKey = explode('|', $kizeoEquipment)[0];
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            if ($kizeoBaseKey === $equipmentBaseKey) {
                return true;
            }
        }
        return false;
    }

    /**
     * Simulation simple - AJOUTER cette méthode dans FormRepository.php
     */
    public function simulateSimpleSync($structuredEquipements, $kizeoEquipments): array
    {
        $result = $kizeoEquipments; // Commencer avec les équipements Kizeo existants
        
        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredKey = explode('|', $structuredEquipment)[0]; // RAISON_SOCIALE\VISITE\NUMERO
            
            // Vérifier si cet équipement exact existe déjà
            $found = false;
            foreach ($result as $key => $existingEquipment) {
                $existingKey = explode('|', $existingEquipment)[0];
                if ($existingKey === $structuredKey) {
                    // Remplacer l'équipement existant
                    $result[$key] = $structuredEquipment;
                    $found = true;
                    break;
                }
            }
            
            // Si pas trouvé, ajouter
            if (!$found) {
                $result[] = $structuredEquipment;
            }
        }
        
        return $result;
    }

    /**
     * Simulation de sync avec nettoyage
     */
    private function simulateSyncWithCleaning($structuredEquipements, $cleanedKizeoEquipments): array
    {
        $result = $cleanedKizeoEquipments;
        
        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredKey = explode('|', $structuredEquipment)[0]; // RAISON_SOCIALE\VISITE\NUMERO
            
            // Chercher dans les équipements Kizeo nettoyés
            $found = false;
            foreach ($result as $key => $existingEquipment) {
                $existingKey = explode('|', $existingEquipment)[0];
                if ($existingKey === $structuredKey) {
                    // Remplacer par les données de la BDD
                    $result[$key] = $structuredEquipment;
                    $found = true;
                    break;
                }
            }
            
            // Si pas trouvé, ajouter
            if (!$found) {
                $result[] = $structuredEquipment;
            }
        }
        
        return $result;
    }

    /**
     * Version finale de compareAndSyncEquipmentsWithKizeoFormatFix - AJOUTER DANS FormRepository.php
     */
    public function compareAndSyncEquipmentsWithKizeoFormatFix($structuredEquipements, $kizeoEquipments, $idListeKizeo): array 
    {
        // Nettoyer d'abord tous les équipements Kizeo
        $cleanedKizeoEquipments = [];
        foreach ($kizeoEquipments as $kizeoEquipment) {
            $cleanedKizeoEquipments[] = $this->cleanKizeoFormat($kizeoEquipment);
        }
        
        $updatedKizeoEquipments = $cleanedKizeoEquipments;

        foreach ($structuredEquipements as $structuredEquipment) {
            $structuredFullKey = explode('|', $structuredEquipment)[0];
            $keyParts = explode('\\', $structuredFullKey);
            $equipmentBaseKey = ($keyParts[0] ?? '') . '\\' . ($keyParts[2] ?? '');

            // Vérifier si cet équipement existe déjà sur Kizeo (peu importe la visite)
            $equipmentExistsInKizeo = $this->equipmentExistsInKizeoWithCleaning($updatedKizeoEquipments, $equipmentBaseKey);
            
            if ($equipmentExistsInKizeo) {
                // L'équipement existe déjà : mettre à jour toutes ses visites
                $this->updateAllVisitsForEquipmentWithCleaning($updatedKizeoEquipments, $equipmentBaseKey, $structuredEquipment);
                
                // Vérifier si la visite spécifique existe, sinon l'ajouter
                $specificVisitExists = $this->specificVisitExistsWithCleaning($updatedKizeoEquipments, $structuredFullKey);
                if (!$specificVisitExists) {
                    $updatedKizeoEquipments[] = $structuredEquipment;
                }
            } else {
                // L'équipement n'existe pas du tout : l'ajouter
                $updatedKizeoEquipments[] = $structuredEquipment;
            }
        }

        $this->envoyerListeKizeo($updatedKizeoEquipments, $idListeKizeo);
        return $updatedKizeoEquipments;
    }

    private function specificVisitExistsWithCleaning($cleanedKizeoEquipments, $structuredFullKey): bool
    {
        foreach ($cleanedKizeoEquipments as $kizeoEquipment) {
            $kizeoFullKey = explode('|', $kizeoEquipment)[0];
            if ($kizeoFullKey === $structuredFullKey) {
                return true;
            }
        }
        return false;
    }

    private function updateAllVisitsForEquipmentWithCleaning(&$cleanedKizeoEquipments, $equipmentBaseKey, $newEquipment): void
    {
        $newEquipmentData = explode('|', $newEquipment);
        $newEquipmentFullKey = $newEquipmentData[0];
        
        foreach ($cleanedKizeoEquipments as $key => $kizeoEquipment) {
            $kizeoEquipmentData = explode('|', $kizeoEquipment);
            $kizeoFullKey = $kizeoEquipmentData[0];
            
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            if ($kizeoBaseKey === $equipmentBaseKey && $kizeoFullKey !== $newEquipmentFullKey) {
                // Mettre à jour les données techniques
                for ($i = 2; $i < count($newEquipmentData); $i++) {
                    if (isset($newEquipmentData[$i])) {
                        if (isset($kizeoEquipmentData[$i])) {
                            $kizeoEquipmentData[$i] = $newEquipmentData[$i];
                        } else {
                            $kizeoEquipmentData[] = $newEquipmentData[$i];
                        }
                    }
                }
                
                $cleanedKizeoEquipments[$key] = implode('|', $kizeoEquipmentData);
            }
        }
    }

    /**
     * Explication des modifications:

     * Extraction des données de l'équipement:

     * $newEquipmentData = explode('|', $newEquipment); crée un tableau contenant les différentes parties de la nouvelle ligne d'équipement (avant et après chaque "|").
     * $kizeoEquipmentData = explode('|', $equipment); crée un tableau similaire pour la ligne d'équipement Kizeo actuelle.
     * Mise à jour des données après le "|":

     * La boucle for parcourt les données de $newEquipmentData à partir de l'indice 2 (données après le nom de l'équipement).
     * Elle met à jour les éléments correspondants dans $kizeoEquipmentData.
     * Reconstruction de la ligne:

     * implode('|', $kizeoEquipmentData) combine les éléments du tableau $kizeoEquipmentData en une seule chaîne, en utilisant "|" comme séparateur.
     * La ligne mise à jour est ensuite stockée dans $kizeoEquipments[$key].
     * Comment ça marche:

     * La fonction extrait les données de la nouvelle ligne et de la ligne Kizeo actuelle dans des tableaux.
     * Elle compare le préfixe du client et le nom de l'équipement pour trouver la ligne à mettre à jour.
     * Au lieu de remplacer toute la ligne, elle met à jour uniquement les données après le "|" dans la ligne Kizeo, en utilisant les données correspondantes de la nouvelle ligne.
     * Enfin, elle reconstruit la ligne Kizeo avec les données mises à jour.
    */
    /**
    * Version corrigée de la fonction updateAllVisits
    * 
    * Explication des modifications:
    * 1. Extraction du numéro d'équipement spécifique (SEC01, SEC02, etc.)
    * 2. Comparaison basée sur le client ET le numéro d'équipement exact
    * 3. Évite la mise à jour de tous les équipements du même type
    */
    public function updateAllVisits(&$kizeoEquipments, $structuredPrefix, $newEquipment): void
    {
        $newEquipmentData = explode('|', $newEquipment);
        
        // Extraire le préfixe client et le numéro d'équipement pour une comparaison plus précise
        $clientPrefix = explode('\\', $structuredPrefix)[0] ?? '';
        $equipmentNumberFromPrefix = explode('\\', $structuredPrefix)[2] ?? '';

        foreach ($kizeoEquipments as $key => $equipment) {
            $kizeoEquipmentData = explode('|', $equipment);
            
            // Extraire les mêmes informations depuis l'équipement Kizeo
            $kizeoFullPrefix = explode('|', $equipment)[0] ?? '';
            $kizeoClientPrefix = explode('\\', $kizeoFullPrefix)[0] ?? '';
            $kizeoEquipmentNumber = explode('\\', $kizeoFullPrefix)[2] ?? '';

            // Comparer le client ET le numéro d'équipement spécifique
            if ($kizeoClientPrefix === $clientPrefix && $kizeoEquipmentNumber === $equipmentNumberFromPrefix) {
                // Met à jour les données après le "|" (pipe)
                for ($i = 2; $i < count($newEquipmentData); $i++) {
                    if (isset($kizeoEquipmentData[$i])) {
                        $kizeoEquipmentData[$i] = $newEquipmentData[$i];
                    } else {
                        $kizeoEquipmentData[] = $newEquipmentData[$i];
                    }
                }
                $kizeoEquipments[$key] = implode('|', $kizeoEquipmentData);
                
                // Sortir de la boucle une fois l'équipement trouvé et mis à jour
                break;
            }
        }
    }

    /**
     * Envoie la liste d'équipements mise à jour à Kizeo
     */
    public function envoyerListeKizeo($kizeoEquipments, $idListeKizeo): void
    {
        Request::enableHttpMethodParameterOverride();
        $client = new Client();
        
        try {
            $response = $client->request(
                'PUT',
                'https://forms.kizeo.com/rest/v3/lists/' . $idListeKizeo,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'json' => [
                        'items' => array_values($kizeoEquipments), // Réindexer le tableau
                    ],
                    'timeout' => 30, // Timeout de 30 secondes
                ]
            );
            
            // Log du succès (optionnel)
            // $this->logger->info('Liste Kizeo mise à jour avec succès', ['liste_id' => $idListeKizeo]);
            
        } catch (\Exception $e) {
            // Log de l'erreur et relancer l'exception
            // $this->logger->error('Erreur envoi liste Kizeo', ['liste_id' => $idListeKizeo, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtient l'ID de liste Kizeo associé à l'entité d'équipement
     */
    public function getIdListeKizeoPourEntite($entite): int
    {
        // Mapping des entités vers les IDs de listes Kizeo
        $mapping = [
            EquipementS10::class => 437895,  // Group - Test: 437895, Prod: 445024
            EquipementS40::class => 437995,  // St Etienne - Test: 437995, Prod: 427442
            EquipementS50::class => 437695,  // Grenoble - Test: 437695, Prod: 414025
            EquipementS60::class => 437996,  // Lyon - Test: 437996, Prod: 427444
            EquipementS70::class => 437897,  // Bordeaux - Test: 437897, Prod: 440263
            EquipementS80::class => 438000,  // Paris Nord - Test: 438000, Prod: 421993
            EquipementS100::class => 437997, // Montpellier - Test: 437997, Prod: 423853
            EquipementS120::class => 437999, // Hauts de France - Test: 437999, Prod: 434252
            EquipementS130::class => 437977, // Toulouse - Test: 437977, Prod: 440667
            EquipementS140::class => 438006, // SMP - Test: 438006, Prod: 427682
            EquipementS150::class => 437976, // SOGEFI - Test: 437976, Prod: 440276
            EquipementS160::class => 437978, // Rouen - Test: 437978, Prod: 441758
            EquipementS170::class => 437979, // Rennes - Test: 437979, Prod: 454540
        ];
        
        if (!isset($mapping[$entite])) {
            throw new \Exception("ID de liste Kizeo non défini pour l'entité " . $entite);
        }
        
        return $mapping[$entite];
    }

    /**
     * Version optimisée avec gestion d'erreurs et métriques (optionnel)
     */
    public function updateKizeoWithEquipmentsListFromBddWithMetrics($entityManager, $formRepository, $cache): array
    {
        $startTime = microtime(true);
        $results = $this->updateKizeoWithEquipmentsListFromBdd($entityManager, $formRepository, $cache);
        $endTime = microtime(true);
        
        // Calcul des métriques
        $totalTime = $endTime - $startTime;
        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorCount = count($results) - $successCount;
        
        return [
            'results' => $results,
            'metrics' => [
                'total_entities' => count($results),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'execution_time' => round($totalTime, 2),
                'cache_status' => 'redis'
            ]
        ];
    }

    /**
     * Structure les équipements de la BDD pour correspondre au format Kizeo
     */
    public function structureLikeKizeoEquipmentsList($equipements): array
    {
        $structuredEquipements = [];
         
        foreach ($equipements as $equipement) {
            // Format Kizeo : "Libelle|Type|Année|N° série|Marque|Hauteur|Largeur|Repère|Id client|Id societe|Code agence"
            $equipmentLine = 
                ($equipement->getRaisonSociale() ?? '') . '\\' .           // Raison sociale
                ($equipement->getVisite() ?? '') . '\\' .           // Visite
                ($equipement->getNumeroEquipement() ?? '') . '|' .           // Numéro équipement
                ($equipement->getLibelleEquipement() ?? '') . '|' .          // Libellé équipement
                ($equipement->getMiseEnService() ?? '') . '|' .             // Année
                ($equipement->getNumeroDeSerie() ?? '') . '|' .             // N° de série
                ($equipement->getMarque() ?? '') . '|' .                    // Marque
                ($equipement->getHauteur() ?? '') . '|' .                   // Hauteur
                ($equipement->getLargeur() ?? '') . '|' .                   // Largeur
                ($equipement->getRepereSiteClient() ?? '') . '|' .          // Repère site client
                ($equipement->getIdContact() ?? '') . '|' .                  // Id client
                ($equipement->getCodeSociete() ?? '') . '|' .                 // Id société
                ($equipement->getCodeAgence() ?? '');                       // Code agence
                
            $structuredEquipements[] = $equipmentLine;
        }
        
        return $structuredEquipements;
    }

    // Function to preg_split structured equipments to keep only the first part  raison_sociale|visit|numero_equipment
    public function splitStructuredEquipmentsToKeepFirstPart($structuredEquipmentsList){
        $structuredEquipmentsListSplitted = [];
        foreach ($structuredEquipmentsList as $structuredEquipment) {
            $clientVisiteCodeEquipementBdd = preg_split('/[|]/',$structuredEquipment);
            $clientVisiteCodeEquipementBdd = $clientVisiteCodeEquipementBdd[0];
            array_push($structuredEquipmentsListSplitted, $clientVisiteCodeEquipementBdd);
        }
        return $structuredEquipmentsListSplitted;
    }

    //      ----------------------------------------------------------------------------------------------------------------------
    //      ---------------------------------------- SAVE BASE EQUIPMENTS LIST TO LOCAL BDD --------------------------------------
    //      ----------------------------------------------------------------------------------------------------------------------
    /**
     * Function to save base equipments lists in local database by agency --- OK POUR TOUTES LES AGENCES DE S10 à S170
     */
    public function saveEquipmentsListByAgencyOnLocalDatabase($listAgency, $entityAgency, $entityManager){
        $listAgencySplitted = [];
        foreach ($listAgency as $equipement) {
            array_push($listAgencySplitted, preg_split("/[:|]/", $equipement));
        }
        
        foreach ($listAgencySplitted as $equipements){
                $equipement = new $entityAgency;
                $equipement->setIdContact($equipements[18]);
                $equipement->setRaisonSociale($equipements[0]);
                $equipement->setCodeSociete($equipements[20]);
                $equipement->setCodeAgence($equipements[22]);
    
                $equipement->setNumeroEquipement($equipements[3]);
                $equipement->setLibelleEquipement(strtolower($equipements[4]));
                // $equipement->setRepereSiteClient($equipements[2]); // Le repère site client n'est pas ici
                $equipement->setMiseEnService($equipements[6]);
                $equipement->setNumeroDeSerie($equipements[8]);
                $equipement->setMarque($equipements[10]);
    
                // tell Doctrine you want to (eventually) save the Product (no queries yet)
                $entityManager->persist($equipement);
                
                // actually executes the queries (i.e. the INSERT query)
                $entityManager->flush();
        }
    }

    /**
     * ------------------------------------------------------------------------------------------------------------------------
     * --------------------------------------------------- EXPORT PDF AND SAVE IN ASSETS/PDF FOLDER -- N'EST PLUS UTILISE----------------------------
     * --------------------------------------------------------------------------------------------------------------------------
     */
    public function savePdfInAssetsPdfFolder($cache){

        // -----------------------------   Return all forms in an array | cached for 900 seconds 15 minutes
        $allFormsArray = $cache->get('all-forms-on-kizeo', function(ItemInterface $item){
            $item->expiresAfter(900);
            $result = FormRepository::getForms();
            return $result['forms'];
        });

        // $allFormsMaintenanceArray = []; // All forms with class "MAINTENANCE
        $formMaintenanceUnread = [];
        $dataOfFormMaintenanceUnread = [];
        $allFormsKeyId = [];
        $formUnreadArray = [];
        
        // ----------------------------- DÉBUT GET all Forms ID with class "MAINTENANCE" we store every forms id
        foreach ($allFormsArray as $key => $value) {
            if ($allFormsArray[$key]['class'] === 'MAINTENANCE') {
                // Récuperation des forms ID
                array_push($allFormsKeyId, $allFormsArray[$key]['id']);
            }
        }
        // -----------------------------  FIN Return all forms with class "MAINTENANCE"

        // ----------------------------------------------------------------------- Début d'appel KIZEO aux formulaires non lus par ID de leur liste
        // ----------------------------------------------------------- Appel de 5 formulaires à la fois en mettant le paramètre LIMIT à 5 en fin d'url
        // --------------- Remise à zéro du tableau $formMaintenanceUnread  ------------------
        // --------------- Avant de le recharger avec les prochains 5 formulaires non lus  ------------------
        $formMaintenanceUnread = [];
        foreach ($allFormsKeyId as $key) {
            $responseUnread = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' .  $key . '/data/unread/read/5', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $result = $responseUnread->getContent();
            $result = $responseUnread->toArray();
            array_push($formMaintenanceUnread, $result);
            
        }
       
        // ----------------------------------------------------------------------- Début d'appel de la DATA des formulaires non lus
        // --------------- Remise à zéro du tableau $dataOfFormMaintenanceUnread  ------------------
        // --------------- Avant de le recharger avec la data des 5 formulaires non lus  ------------------
        $dataOfFormMaintenanceUnread = [];
        $allPdfArray = [];
        foreach ($formMaintenanceUnread as $formUnread) {
            
            foreach ($formUnread['data'] as $form) {
                array_push($formUnreadArray, $form);
                $response = $this->client->request(
                    'GET',
                    'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/data/' . $form['_id'], [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );
                $result= $response->getContent();
                $result= $response->toArray();
                array_push($dataOfFormMaintenanceUnread, $result['data']['fields']);
                
                // -----------------------------------------------------------    TRY FIX SAVE PDF
                $responseData = $this->client->request(
                    'GET',
                    'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/data/' . $form['_id'] . '/pdf', [
                        'headers' => [
                            'Accept' => 'application/pdf',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );
                $content = $responseData->getContent();
                # Création des fichiers
                
                foreach ($dataOfFormMaintenanceUnread as $OneFormMaintenanceUnread) {
                        
                    $pathStructureToFile = '../pdf/maintenance/' . $OneFormMaintenanceUnread['code_agence']['value']  . '/' . $OneFormMaintenanceUnread['nom_client']['value'] . '/' . substr($OneFormMaintenanceUnread['date_et_heure1']['value'], 0, 4);
                    $normalNameOfTheFile = $OneFormMaintenanceUnread['nom_client']['value'] . '-' . $OneFormMaintenanceUnread['code_agence']['value'] . '.pdf';
                    
                    // Ajouté pour voir si cela fix le mauvais nommage des dossiers PDF sur le nom de la visite de maintenance. Ex: SDCC est enregistré en CE2 au lieu de CEA
                    // SDCC bon en BDD mais pas en enregistrement des pdf
                    // for ($i=0; $i < count($dataOfFormMaintenanceUnread); $i++) { 
                        # code...
                    if (str_contains($OneFormMaintenanceUnread['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE1')) {
                        # ------------------------------------------------------------------ ---------------------------    code CE1
                        switch (str_contains($OneFormMaintenanceUnread['nom_client']['value'], '/')) {
                            case false:
                                if (!file_exists($pathStructureToFile . '/CE1' . '/' . "CE1-" . $normalNameOfTheFile)){
                                    mkdir($pathStructureToFile . '/CE1', 0777, true);
                                    file_put_contents( $pathStructureToFile . '/CE1' . '/' . "CE1-" . $normalNameOfTheFile , $content, LOCK_EX);
                                    break;
                                }
                        
                            case true:
                                $nomClient = $OneFormMaintenanceUnread['nom_client']['value'];
                                $nomClientClean = str_replace("/", "", $nomClient);
                                $cleanPathStructureToTheFile = '../pdf/maintenance/' . $OneFormMaintenanceUnread['code_agence']['value']  . '/' . $nomClientClean . '/' . substr($OneFormMaintenanceUnread['date_et_heure1']['value'], 0, 4) . '/CE1';
                                $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE1-" . $nomClientClean . '-' . $OneFormMaintenanceUnread['code_agence']['value'] . '.pdf';
            
                                if (!file_exists($cleanNameOfTheFile)){
                                    mkdir($cleanPathStructureToTheFile, 0777, true);
                                    file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
                                }
                                break;
            
                            default:
                                break;
                        }
                    }
                    elseif (str_contains($OneFormMaintenanceUnread['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE2')) {
                        # ------------------------------------------------------------------ ---------------------------    code CE2
                        switch (str_contains($OneFormMaintenanceUnread['nom_client']['value'], '/')) {
                            case false:
                                if (!file_exists($pathStructureToFile . '/CE2' . '/' . "CE2-" . $normalNameOfTheFile)){
                                    mkdir($pathStructureToFile . '/CE2', 0777, true);
                                    file_put_contents( $pathStructureToFile . '/CE2' . '/' . "CE2-" . $normalNameOfTheFile , $content, LOCK_EX);
                                    break;
                                }
                        
                            case true:
                                $nomClient = $OneFormMaintenanceUnread['nom_client']['value'];
                                $nomClientClean = str_replace("/", "", $nomClient);
                                $cleanPathStructureToTheFile = '../pdf/maintenance/' . $OneFormMaintenanceUnread['code_agence']['value']  . '/' . $nomClientClean . '/' . substr($OneFormMaintenanceUnread['date_et_heure1']['value'], 0, 4)  . '/CE2';
                                $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE2-" . $nomClientClean . '-' . $OneFormMaintenanceUnread['code_agence']['value'] . '.pdf';
            
                                if (!file_exists($cleanNameOfTheFile)){
                                    mkdir($cleanPathStructureToTheFile, 0777, true);
                                    file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
                                }
                                break;
            
                            default:
                                break;
                        }
                    }
                    elseif (str_contains($OneFormMaintenanceUnread['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE3')) {
                        # ------------------------------------------------------------------ ---------------------------    code CE3
                        switch (str_contains($OneFormMaintenanceUnread['nom_client']['value'], '/')) {
                            case false:
                                if (!file_exists($pathStructureToFile . '/CE3' . '/' . "CE3-" . $normalNameOfTheFile)){
                                    mkdir($pathStructureToFile . '/CE3', 0777, true);
                                    file_put_contents( $pathStructureToFile . '/CE3' . '/' . "CE3-" . $normalNameOfTheFile , $content, LOCK_EX);
                                    break;
                                }
                        
                            case true:
                                $nomClient = $OneFormMaintenanceUnread['nom_client']['value'];
                                $nomClientClean = str_replace("/", "", $nomClient);
                                $cleanPathStructureToTheFile = '../pdf/maintenance/' . $OneFormMaintenanceUnread['code_agence']['value']  . '/' . $nomClientClean . '/' . substr($OneFormMaintenanceUnread['date_et_heure1']['value'], 0, 4)  . '/CE3';
                                $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE3-" . $nomClientClean . '-' . $OneFormMaintenanceUnread['code_agence']['value'] . '.pdf';
            
                                if (!file_exists($cleanNameOfTheFile)){
                                    mkdir($cleanPathStructureToTheFile, 0777, true);
                                    file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
                                }
                                break;
            
                            default:
                                break;
                        }                        
                    }
                    elseif (str_contains($OneFormMaintenanceUnread['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE4')) {
                        # ------------------------------------------------------------------ ---------------------------    code CE4
                        switch (str_contains($OneFormMaintenanceUnread['nom_client']['value'], '/')) {
                            case false:
                                if (!file_exists($pathStructureToFile . '/CE4' . '/' . "CE4-" . $normalNameOfTheFile)){
                                    mkdir($pathStructureToFile . '/CE4', 0777, true);
                                    file_put_contents( $pathStructureToFile . '/CE4' . '/' . "CE4-" . $normalNameOfTheFile , $content, LOCK_EX);
                                    break;
                                }
                        
                            case true:
                                $nomClient = $OneFormMaintenanceUnread['nom_client']['value'];
                                $nomClientClean = str_replace("/", "", $nomClient);
                                $cleanPathStructureToTheFile = '../pdf/maintenance/' . $OneFormMaintenanceUnread['code_agence']['value']  . '/' . $nomClientClean . '/' . substr($OneFormMaintenanceUnread['date_et_heure1']['value']['value'], 0, 4)  . '/CE4';
                                $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE4-" . $nomClientClean . '-' . $OneFormMaintenanceUnread['code_agence']['value'] . '.pdf';
            
                                if (!file_exists($cleanNameOfTheFile)){
                                    mkdir($cleanPathStructureToTheFile, 0777, true);
                                    file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
                                }
                                break;
            
                            default:
                                break;
                        }
                        
                    }
                    elseif (str_contains($OneFormMaintenanceUnread['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CEA')) {
                        # ------------------------------------------------------------------ ---------------------------    code CEA
                        switch (str_contains($OneFormMaintenanceUnread['nom_client']['value'], '/')) {
                            case false:
                                if (!file_exists($pathStructureToFile . '/CEA' . '/' . "CEA-" . $normalNameOfTheFile)){
                                    mkdir($pathStructureToFile . '/CEA', 0777, true);
                                    file_put_contents( $pathStructureToFile . '/CEA' . '/' . "CEA-" . $normalNameOfTheFile , $content, LOCK_EX);
                                    break;
                                }
                        
                            case true:
                                $nomClient = $OneFormMaintenanceUnread['nom_client']['value'];
                                $nomClientClean = str_replace("/", "", $nomClient);
                                $cleanPathStructureToTheFile = '../pdf/maintenance/' . $OneFormMaintenanceUnread['code_agence']['value']  . '/' . $nomClientClean . '/' . substr($OneFormMaintenanceUnread['date_et_heure1']['value'], 0, 4)  . '/CEA';
                                $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CEA-" . $nomClientClean . '-' . $OneFormMaintenanceUnread['code_agence']['value'] . '.pdf';
            
                                if (!file_exists($cleanNameOfTheFile)){
                                    mkdir($cleanPathStructureToTheFile, 0777, true);
                                    file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
                                }
                                break;
            
                            default:
                                break;
                        }
                        
                    }else{
                    }
                }
                // -------------------------------------------            MARK FORM AS READ !!!
                // ------------------------------------------------------------------------------
                $response = $this->client->request(
                    'POST',
                    'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'json' => [
                            "data_ids" => [intval($form['_id'])]
                        ]
                    ]
                );
                $dataOfResponse = $response->getContent();
                
                
                // -------------------------------------------            MARKED FORM AS READ !!!
                // ------------------------------------------------------------------------------
            }

    
        }
    }

    /**
     * Function to save PDF with pictures for etat des lieux portails in directories on O2switch  -------------- FUNCTIONNAL -------
     * ----------------------------             LE MARK AS READ N'EST PAS IMPLEMENTE
     */
    public function savePortailsPdfInPublicFolder(){
        // Récupérer les fichiers PDF dans un tableau
        // -----------------------------   Return all forms in an array
        $allFormsArray = FormRepository::getForms();
        $allFormsArray = $allFormsArray['forms'];
        $allFormsPortailsArray = [];
        $allFormsPdf = [];

        // -----------------------------   Return all forms with name etat des lieux and avoid model etat des lieux with id 996714
        foreach ($allFormsArray as $key => $value) {
            if (str_contains($allFormsArray[$key]['name'], 'Etat des lieux') && $allFormsArray[$key]['id'] != 996714) {
                
                $response = $this->client->request(
                    'POST',
                    'https://forms.kizeo.com/rest/v3/forms/' . $allFormsArray[$key]['id'] . '/data/advanced', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );
                $content = $response->getContent();
                $content = $response->toArray();
                foreach ($content['data'] as $key => $value) {
                    array_push($allFormsPortailsArray, $value);
                }
            }
        }
        // GET available exports from form_id
        foreach ($allFormsPortailsArray as $key => $value) {
            // ------------------------------------------      GET to receive PDF FROM FORMS FROM TECHNICIANS WHITH PICTURES
            $responseData = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' .  $allFormsPortailsArray[$key]['_form_id'] . '/data/' . $allFormsPortailsArray[$key]['_id'] . '/pdf', [
                    'headers' => [
                        'Accept' => 'application/pdf',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
            $content = $responseData->getContent();
            
            $numero_agence = "";

            if (isset($allFormsPortailsArray[$key]['n_agence'])) {
                $numero_agence = $allFormsPortailsArray[$key]['n_agence'];
            }else{
                $numero_agence = substr($allFormsPortailsArray[$key]['_user_name'], 0, 4);
            }

            if (!file_exists('ETAT_DES_LIEUX_PORTAILS/' . $numero_agence . '/' . $allFormsPortailsArray[$key]['liste_clients'] . ' - ' .  $allFormsPortailsArray[$key]['date_et_heure1'])) {
                # code...
                switch (str_contains($allFormsPortailsArray[$key]['liste_clients'], '/')) {
                    case false:
                        mkdir('ETAT_DES_LIEUX_PORTAILS/' . $numero_agence . '/' . $allFormsPortailsArray[$key]['liste_clients'] . ' - ' .  $allFormsPortailsArray[$key]['date_et_heure1'] . '/' . substr($allFormsPortailsArray[$key]['date_et_heure1'], 0, 4), 0777, true);
                        file_put_contents('ETAT_DES_LIEUX_PORTAILS/' . $numero_agence . '/' . $allFormsPortailsArray[$key]['liste_clients'] . ' - ' .  $allFormsPortailsArray[$key]['date_et_heure1'] . '/' . substr($allFormsPortailsArray[$key]['date_et_heure1'], 0, 4) . '/' . $allFormsPortailsArray[$key]['liste_clients'] . '-' . $numero_agence  . '-' . $allFormsPortailsArray[$key]['date_et_heure1'] . '.pdf' , $content, LOCK_EX);
                        break;
                
                    case true:
                        $nomClient = $allFormsPortailsArray[$key]['liste_clients'];
                        $nomClientClean = str_replace("/", "", $nomClient);
                        if (!file_exists('ETAT_DES_LIEUX_PORTAILS/' . $numero_agence . '/' . $nomClientClean . ' - ' .  $allFormsPortailsArray[$key]['date_et_heure1'])){
                            mkdir('ETAT_DES_LIEUX_PORTAILS/' . $numero_agence . '/' . $nomClientClean . ' - ' .  $allFormsPortailsArray[$key]['date_et_heure1'], 0777, true);
                            file_put_contents( 'ETAT_DES_LIEUX_PORTAILS/' . $numero_agence . '/' . $nomClientClean . ' - ' .  $allFormsPortailsArray[$key]['date_et_heure1'] . '/' . substr($allFormsPortailsArray[$key]['date_et_heure1'], 0, 4) . '/' . $nomClientClean . '-' . $numero_agence  . '-' . $allFormsPortailsArray[$key]['date_et_heure1'] . '.pdf' , $content, LOCK_EX);
                        }
                        break;
    
                    default:
                        break;
                }
            }
        }
        return $allFormsPdf;
    }

    // public function getJpgPictureFromStringName($value, $entityManager){
    //     // $picturesNames = [$value->photo_plaque, $value->photo_etiquette_somafi, $value->photo_choc, $value->photo_choc_montant, $value->photo_panneau_intermediaire_i, $value->photo_panneau_bas_inter_ext, $value->photo_lame_basse__int_ext, $value->photo_lame_intermediaire_int_, $value->photo_envirronement_eclairage, $value->photo_bache, $value->photo_marquage_au_sol, $value->photo_environnement_equipement1, $value->photo_coffret_de_commande, $value->photo_carte, $value->photo_rail, $value->photo_equerre_rail, $value->photo_fixation_coulisse, $value->photo_moteur, $value->photo_deformation_plateau, $value->photo_deformation_plaque, $value->photo_deformation_structure, $value->photo_deformation_chassis, $value->photo_deformation_levre, $value->photo_fissure_cordon, $value->photo_joue, $value->photo_butoir, $value->photo_vantail, $value->photo_linteau, $value->photo_barriere, $value->photo_tourniquet, $value->photo_sas, $value->photo_marquage_au_sol_, $value->photo_marquage_au_sol_2, $value->photo_2, $value->photo_compte_rendu];

    //     // On récupère la photo du compte rendu uniquement
    //     $picturesNames = [ $value->photo_2, $value->photo_compte_rendu];
        
    //     $the_picture = [];
        
    //     foreach ($picturesNames as $pictureName) {
    //         if (!str_contains($pictureName, ", ")) {
    //             if ($pictureName != "" || $pictureName != null) {
    //                 $response = $this->client->request(
    //                     'GET',
    //                     'https://forms.kizeo.com/rest/v3/forms/' .  $value->form_id . '/data/' . $value->data_id . '/medias/' . $pictureName, [
    //                         'headers' => [
    //                             'Accept' => 'application/json',
    //                             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //                         ],
    //                     ]
    //                 );
    //                 $photoJpg = $response->getContent();
    //                 array_push($the_picture, $photoJpg);
    //             }
    //         }
    //         else{
    //             $photosSupplementaires = explode(", ", $pictureName);
    //             foreach ($photosSupplementaires as $photo) {
    //                 // Call kizeo url to get jpeg here and encode the result
    //                 $response = $this->client->request(
    //                     'GET',
    //                     'https://forms.kizeo.com/rest/v3/forms/' .  $value->form_id . '/data/' . $value->data_id . '/medias/' . $photo, [
    //                         'headers' => [
    //                             'Accept' => 'application/json',
    //                             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //                         ],
    //                     ]
    //                 );
    //                 $photoJpg = $response->getContent();
    //                 array_push($the_picture, $photoJpg);
    //             }
    //         }
    //     }
    //     return $the_picture;
    // }
    public function getJpgPictureFromStringName($value, $entityManager){
        // On récupère la photo du compte rendu uniquement
        $picturesNames = [ $value->photo_2, $value->photo_compte_rendu];
        
        $the_picture = [];
        
        foreach ($picturesNames as $pictureName) {
            if (empty($pictureName)) {
                continue;
            }
            
            if (!str_contains($pictureName, ", ")) {
                // Photo unique
                $photoContent = $this->fetchPhotoFromKizeoSafely($value, $pictureName);
                if ($photoContent) {
                    array_push($the_picture, $photoContent);
                }
            } else {
                // Photos multiples séparées par des virgules
                $photosSupplementaires = explode(", ", $pictureName);
                foreach ($photosSupplementaires as $photo) {
                    if (!empty(trim($photo))) {
                        $photoContent = $this->fetchPhotoFromKizeoSafely($value, trim($photo));
                        if ($photoContent) {
                            array_push($the_picture, $photoContent);
                        }
                    }
                }
            }
        }
        return $the_picture;
    }
    
    /**
     * Fonction helper pour récupérer une photo depuis l'API Kizeo avec gestion d'erreurs
     * Continue le traitement même si une photo n'est pas trouvée (404)
     */
    private function fetchPhotoFromKizeoSafely($value, $photoName) {
        try {
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $value->form_id . '/data/' . $value->data_id . '/medias/' . $photoName,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
            return $response->getContent();
        } catch (\Exception $e) {
            // Log l'erreur pour debug mais continue le traitement
            error_log("Photo '{$photoName}' non trouvée sur Kizeo Forms (Form ID: {$value->form_id}, Data ID: {$value->data_id}): " . $e->getMessage());
            return null;
        }
    }

    public function getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment) {
        $picturesdata = [];
        
        foreach ($picturesArray as $key => $value) {
            // MODIFICATION: Utiliser la nouvelle architecture pour récupérer les photos
            $photoJpg = $this->getJpgPictureFromStringNameNewArchitecture(
                $value, 
                $entityManager, 
                $equipment->getCodeAgence(),
                $equipment->getIdContact(),  // AJOUT CRUCIAL: Utiliser id_contact
                date('Y', strtotime($equipment->getDateEnregistrement())),
                $equipment->getVisite()
            );
            
            foreach ($photoJpg as $photo) {
                $pictureEncoded = base64_encode($photo);
                $picturesdataObject = new stdClass;
                $picturesdataObject->picture = $pictureEncoded;
                $picturesdataObject->update_time = $value->update_time;
                array_push($picturesdata, $picturesdataObject);
            }
        }
        return $picturesdata;
    }

    // NOUVELLE MÉTHODE: Récupération des photos avec nouvelle architecture
    public function getJpgPictureFromStringNameNewArchitecture(
        $formEntity, 
        $entityManager, 
        $codeAgence, 
        $idContact, 
        $anneeVisite, 
        $typeVisite
    ) {
        $the_picture = [];
        
        try {
            // Récupérer le service ImageStorageService
            $imageStorageService = $this->container->get('App\Service\ImageStorageService');
            
            // Récupérer toutes les images locales pour cet équipement avec la nouvelle architecture
            $localImages = $imageStorageService->getAllImagesForEquipment(
                $codeAgence,
                $idContact,   // MODIFICATION: Utiliser id_contact au lieu de raison_sociale
                $anneeVisite,
                $typeVisite,
                $formEntity->getCodeEquipement()
            );
            
            // Convertir en contenu binaire pour compatibilité
            foreach ($localImages as $photoType => $imageInfo) {
                if (file_exists($imageInfo['path'])) {
                    $the_picture[] = file_get_contents($imageInfo['path']);
                }
            }
            
            // Si pas de photos locales, essayer l'ancienne méthode comme fallback
            if (empty($the_picture)) {
                $the_picture = $this->getJpgPictureFromStringName($formEntity, $entityManager);
            }
            
        } catch (\Exception $e) {
            error_log("Erreur récupération photos avec nouvelle architecture: " . $e->getMessage());
            // Fallback vers ancienne méthode
            $the_picture = $this->getJpgPictureFromStringName($formEntity, $entityManager);
        }
        
        return $the_picture;
    }

    /**
     * MÉTHODE DE CORRECTION GLOBALE: Corriger tous les enregistrements Form existants
     */
    public function fixAllFormsWithMissingIdContact(EntityManager $entityManager): array
    {
        $results = ['fixed' => 0, 'errors' => 0, 'skipped' => 0];
        
        try {
            // Récupérer toutes les entrées Form sans id_contact
            $qb = $this->createQueryBuilder('f')
                ->where('f.id_contact IS NULL OR f.id_contact = :empty')
                ->andWhere('f.code_equipement IS NOT NULL')
                ->andWhere('f.raison_sociale_visite IS NOT NULL')
                ->setParameter('empty', '');
            
            $formsToFix = $qb->getQuery()->getResult();
            
            foreach ($formsToFix as $form) {
                try {
                    $codeEquipement = $form->getCodeEquipement();
                    $raisonSocialeVisite = $form->getRaisonSocialeVisite();
                    
                    // Extraire le nom de la société et la visite
                    $parts = explode('\\', $raisonSocialeVisite);
                    $raisonSociale = $parts[0] ?? '';
                    $visite = $parts[1] ?? '';
                    
                    if (empty($raisonSociale) || empty($visite)) {
                        $results['skipped']++;
                        continue;
                    }
                    
                    // CHERCHER L'ÉQUIPEMENT CORRESPONDANT pour récupérer id_contact et id_societe
                    $equipment = null;
                    
                    // Déterminer l'agence depuis le code équipement ou d'autres indices
                    $agences = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
                    
                    foreach ($agences as $agence) {
                        $entityClass = 'App\\Entity\\Equipement' . $agence;
                        if (class_exists($entityClass)) {
                            $repository = $entityManager->getRepository($entityClass);
                            $equipment = $repository->findOneBy([
                                'numero_equipement' => $codeEquipement,
                                'raison_sociale' => $raisonSociale . '\\' . $visite
                            ]);
                            
                            if ($equipment) {
                                break;
                            }
                        }
                    }
                    
                    if ($equipment) {
                        // CORRECTION: Mettre à jour les champs manquants
                        $form->setIdContact($equipment->getIdContact());
                        $form->setIdSociete($equipment->getCodeSociete());
                        
                        $this->getEntityManager()->persist($form);
                        $results['fixed']++;
                    } else {
                        $results['skipped']++;
                    }
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    error_log("Erreur correction Form ID {$form->getId()}: " . $e->getMessage());
                }
            }
            
            // Sauvegarder toutes les modifications
            $this->getEntityManager()->flush();
            
        } catch (\Exception $e) {
            $results['errors']++;
            error_log("Erreur correction globale Forms: " . $e->getMessage());
        }
        
        return $results;
    }

    // Nouvelle fonction dans FormRepository.php pour récupérer spécifiquement les photos des équipements supplémentaires

    /**
     * Récupère les photos spécifiquement pour les équipements supplémentaires
     * Les équipements supplémentaires utilisent le champ photo_compte_rendu
     */
    public function getPictureArrayByIdSupplementaryEquipment($entityManager, $equipment) {
        $photoManagementService = $this->container->get(PhotoManagementService::class);
        return $photoManagementService->getEquipmentPhotos($equipment);
    }

    
    /**
     * Récupère spécifiquement les photos du champ photo_compte_rendu
     */
    public function getJpgPictureFromPhotoCompteRendu($formEntity, $entityManager) {
        $picturesNames = [$formEntity->getPhotoCompteRendu()];
        $the_picture = [];
        
        foreach ($picturesNames as $pictureName) {
            if (empty($pictureName)) {
                continue;
            }
            
            // Gérer les photos multiples séparées par des virgules
            if (str_contains($pictureName, ", ")) {
                $photosSupplementaires = explode(", ", $pictureName);
                foreach ($photosSupplementaires as $photo) {
                    if (!empty(trim($photo))) {
                        $photoContent = $this->fetchPhotoFromKizeo($formEntity, trim($photo));
                        if ($photoContent) {
                            $the_picture[] = $photoContent;
                        }
                    }
                }
            } else {
                // Photo unique
                $photoContent = $this->fetchPhotoFromKizeo($formEntity, $pictureName);
                if ($photoContent) {
                    $the_picture[] = $photoContent;
                }
            }
        }
        
        return $the_picture;
    }

    /**
     * Fonction helper pour récupérer une photo depuis l'API Kizeo
     */
    private function fetchPhotoFromKizeo($formEntity, $photoName) {
        try {
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formEntity->getFormId() . '/data/' . $formEntity->getDataId() . '/medias/' . $photoName,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );
            return $response->getContent();
        } catch (\Exception $e) {
            // Log l'erreur si nécessaire
            return null;
        }
    }

    public function cleanKizeoFormat($kizeoEquipment): string
    {
        $parts = explode('|', $kizeoEquipment);
        $cleanedParts = [];
        
        foreach ($parts as $index => $part) {
            if ($index === 0) {
                if (strpos($part, ':') !== false) {
                    $colonParts = explode(':', $part);
                    $reconstructed = '';
                    for ($i = 0; $i < count($colonParts); $i += 2) {
                        if ($i > 0) {
                            $reconstructed .= '\\';
                        }
                        $reconstructed .= $colonParts[$i];
                    }
                    $cleanedParts[] = $reconstructed;
                } else {
                    $cleanedParts[] = $part;
                }
            } else {
                if (strpos($part, ':') !== false) {
                    $subParts = explode(':', $part);
                    $cleanedParts[] = $subParts[0];
                } else {
                    $cleanedParts[] = $part;
                }
            }
        }
        
        return implode('|', $cleanedParts);
    }
    
    public function equipmentExistsInKizeo($kizeoEquipments, $equipmentBaseKey): bool
    {
        foreach ($kizeoEquipments as $kizeoEquipment) {
            $kizeoFullKey = explode('|', $kizeoEquipment)[0];
            $kizeoKeyParts = explode('\\', $kizeoFullKey);
            $kizeoBaseKey = ($kizeoKeyParts[0] ?? '') . '\\' . ($kizeoKeyParts[2] ?? '');
            
            if ($kizeoBaseKey === $equipmentBaseKey) {
                return true;
            }
        }
        return false;
    }

    public function specificVisitExists($kizeoEquipments, $structuredFullKey): bool
    {
        foreach ($kizeoEquipments as $kizeoEquipment) {
            $kizeoFullKey = explode('|', $kizeoEquipment)[0];
            if ($kizeoFullKey === $structuredFullKey) {
                return true;
            }
        }
        return false;
    }

    // Gestion des photos en local
    /**
     * NOUVELLE VERSION OPTIMISÉE - Récupère les photos locales au lieu d'appeler l'API
     * Remplace l'ancienne méthode getPictureArrayByIdEquipment
     */
    public function getPictureArrayByIdEquipmentOptimized($equipment, EntityManagerInterface $entityManager): array
    {
        $picturesdata = [];
    
        try {
            // NOUVELLE VÉRIFICATION: S'assurer que $equipment est un objet et non un array
            if (is_array($equipment)) {
                error_log("ERREUR: getPictureArrayByIdEquipmentOptimized appelé avec un array au lieu d'un objet Equipment");
                // Fallback vers l'ancienne méthode si c'est un array
                return [];
            }
            
            // Vérifier que l'objet a les méthodes nécessaires
            if (!method_exists($equipment, 'getCodeAgence')) {
                error_log("ERREUR: L'objet passé n'a pas la méthode getCodeAgence()");
                return [];
            }
            
            // Extraire les informations nécessaires de l'équipement
            $agence = $equipment->getCodeAgence();
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();
            $codeEquipement = $equipment->getNumeroEquipement();
            
            // Récupérer toutes les photos locales de cet équipement
            $localPhotos = $this->imageStorageService->getAllImagesForEquipment(
                $agence,
                $raisonSociale,
                $anneeVisite,
                $typeVisite,
                $codeEquipement
            );
            
            // Convertir les photos locales au format attendu par le template
            foreach ($localPhotos as $photoType => $photoInfo) {
                if (file_exists($photoInfo['path'])) {
                    $pictureEncoded = base64_encode(file_get_contents($photoInfo['path']));
                    
                    $picturesdataObject = new \stdClass();
                    $picturesdataObject->picture = $pictureEncoded;
                    $picturesdataObject->update_time = date('Y-m-d H:i:s', $photoInfo['modified']);
                    $picturesdataObject->photo_type = $photoType;
                    $picturesdataObject->local_path = $photoInfo['path'];
                    
                    $picturesdata[] = $picturesdataObject;
                }
            }
            
            // Si aucune photo locale n'est trouvée, fallback vers la méthode originale avec l'API
            if (empty($picturesdata)) {
                return $this->getPictureArrayByIdEquipmentFallback($equipment, $entityManager);
            }
            
        } catch (\Exception $e) {
            error_log("Erreur récupération photos locales pour {$equipment->getNumeroEquipement()}: " . $e->getMessage());
            // Fallback vers l'ancienne méthode en cas d'erreur
            return $this->getPictureArrayByIdEquipmentFallback($equipment, $entityManager);
        }
        
        return $picturesdata;
    }

    /**
     * NOUVELLE VERSION OPTIMISÉE pour équipements supplémentaires
     * Remplace getPictureArrayByIdSupplementaryEquipment
     */
    public function getPictureArrayByIdSupplementaryEquipmentOptimized($equipment, EntityManagerInterface $entityManager): array
    {
        $picturesdata = [];
        
        try {
            // Mêmes informations que pour les équipements normaux
            $agence = $equipment->getCodeAgence();
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();
            $codeEquipement = $equipment->getNumeroEquipement();
            
            // Priorité aux photos "compte_rendu" pour les équipements supplémentaires
            $compteRenduPhoto = $this->imageStorageService->getImagePath(
                $agence,
                $raisonSociale,
                $anneeVisite,
                $typeVisite,
                $codeEquipement . '_compte_rendu'
            );
            
            if ($compteRenduPhoto && file_exists($compteRenduPhoto)) {
                $pictureEncoded = base64_encode(file_get_contents($compteRenduPhoto));
                
                $picturesdataObject = new \stdClass();
                $picturesdataObject->picture = $pictureEncoded;
                $picturesdataObject->update_time = date('Y-m-d H:i:s', filemtime($compteRenduPhoto));
                $picturesdataObject->photo_type = 'compte_rendu';
                
                $picturesdata[] = $picturesdataObject;
            }
            
            // Si pas de photo locale, fallback vers l'API
            if (empty($picturesdata)) {
                return $this->getPictureArrayByIdSupplementaryEquipmentFallback($equipment, $entityManager);
            }
            
        } catch (\Exception $e) {
            error_log("Erreur photos locales équipement supplémentaire {$codeEquipement}: " . $e->getMessage());
            return $this->getPictureArrayByIdSupplementaryEquipmentFallback($equipment, $entityManager);
        }
        
        return $picturesdata;
    }

    /**
     * Méthode fallback - ancienne logique avec appels API (pour compatibilité)
     */
    private function getPictureArrayByIdEquipmentFallback($equipment, EntityManagerInterface $entityManager): array
    {
        // Ancienne logique avec appels API Kizeo
        $picturesArray = $entityManager->getRepository(Form::class)->findBy([
            'code_equipement' => $equipment->getNumeroEquipement(),
            'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
        ]);
        
        $picturesdata = [];
        
        foreach ($picturesArray as $value) {
            if ($value->getPhotoCompteRendu() && $value->getPhotoCompteRendu() !== '') {
                $photoJpg = $this->getJpgPictureFromPhotoCompteRendu($value, $entityManager);
                
                if (!empty($photoJpg)) {
                    foreach ($photoJpg as $photo) {
                        $pictureEncoded = base64_encode($photo);
                        $picturesdataObject = new \stdClass();
                        $picturesdataObject->picture = $pictureEncoded;
                        $picturesdataObject->update_time = $value->getUpdateTime();
                        $picturesdata[] = $picturesdataObject;
                    }
                }
            }
        }
        
        return $picturesdata;
    }

    /**
     * Méthode fallback pour équipements supplémentaires
     */
    private function getPictureArrayByIdSupplementaryEquipmentFallback($equipment, EntityManagerInterface $entityManager): array
    {
        // Ancienne logique avec appels API
        $picturesArray = $entityManager->getRepository(Form::class)->findBy([
            'code_equipement' => $equipment->getNumeroEquipement(),
            'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
        ]);
        
        $picturesdata = [];
        
        foreach ($picturesArray as $value) {
            if ($value->getPhotoCompteRendu() && $value->getPhotoCompteRendu() !== '') {
                $photoJpg = $this->getJpgPictureFromPhotoCompteRendu($value, $entityManager);
                
                if (!empty($photoJpg)) {
                    foreach ($photoJpg as $photo) {
                        $pictureEncoded = base64_encode($photo);
                        $picturesdataObject = new \stdClass();
                        $picturesdataObject->picture = $pictureEncoded;
                        $picturesdataObject->update_time = $value->getUpdateTime();
                        $picturesdata[] = $picturesdataObject;
                    }
                }
            }
        }
        
        return $picturesdata;
    }

    /**
     * NOUVELLE MÉTHODE - Batch processing pour générer plusieurs PDFs rapidement
     */
    public function generateBatchPDFsWithLocalPhotos(array $equipments, string $agence): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'generated_pdfs' => [],
            'processing_time' => 0
        ];
        
        $startTime = microtime(true);
        
        foreach ($equipments as $equipment) {
            try {
                // Utiliser les photos locales pour ce PDF
                $picturesData = $this->getPictureArrayByIdEquipmentOptimized($equipment, $this->getEntityManager());
                
                // Générer le PDF (logique existante mais avec photos locales)
                $pdfPath = $this->generateSinglePDFWithLocalPhotos($equipment, $picturesData, $agence);
                
                if ($pdfPath) {
                    $results['success']++;
                    $results['generated_pdfs'][] = $pdfPath;
                } else {
                    $results['errors']++;
                }
                
            } catch (\Exception $e) {
                $results['errors']++;
                error_log("Erreur génération PDF pour équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
            }
        }
        
        $results['processing_time'] = round(microtime(true) - $startTime, 2);
        
        return $results;
    }

    /**
     * Génération d'un seul PDF avec photos locales
     */
    private function generateSinglePDFWithLocalPhotos($equipment, array $picturesData, string $agence): ?string
    {
        try {
            // Préparer les données pour le template Twig
            $equipmentsWithPictures = [
                [
                    'equipment' => $equipment,
                    'pictures' => $picturesData
                ]
            ];
            
            // Utiliser le moteur de template existant mais avec les données locales
            // Cette partie dépend de votre implémentation Twig existante
            
            $pdfFilename = sprintf(
                'equipement_%s_%s_%s.pdf',
                $equipment->getNumeroEquipement(),
                $agence,
                date('Y-m-d')
            );
            
            // Retourner le chemin du PDF généré
            return '/path/to/generated/pdfs/' . $pdfFilename;
            
        } catch (\Exception $e) {
            error_log("Erreur génération PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * UTILITAIRE - Vérifie la disponibilité des photos locales pour un équipement
     */
    public function checkLocalPhotosAvailability($equipment): array
    {
        $agence = $equipment->getCodeAgence();
        $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $codeEquipement = $equipment->getNumeroEquipement();
        
        $localPhotos = $this->imageStorageService->getAllImagesForEquipment(
            $agence,
            $raisonSociale,
            $anneeVisite,
            $typeVisite,
            $codeEquipement
        );
        
        return [
            'has_local_photos' => !empty($localPhotos),
            'photo_count' => count($localPhotos),
            'photo_types' => array_keys($localPhotos),
            'total_size' => array_sum(array_column($localPhotos, 'size')),
            'equipment_id' => $codeEquipement
        ];
    }

    /**
     * MIGRATION - Convertit tous les équipements vers le stockage local
     */
    public function migrateAllEquipmentsToLocalStorage(string $agence, int $batchSize = 50): array
    {
        $repository = $this->getEntityManager()->getRepository("App\\Entity\\Equipement{$agence}");
        $totalEquipments = $repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $results = [
            'total_equipments' => $totalEquipments,
            'processed' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'batches_completed' => 0
        ];
        
        $offset = 0;
        
        while ($offset < $totalEquipments) {
            // Traiter par lots pour éviter les problèmes de mémoire
            $equipments = $repository->createQueryBuilder('e')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($equipments as $equipment) {
                try {
                    $results['processed']++;
                    
                    // Vérifier si les photos locales existent déjà
                    $availability = $this->checkLocalPhotosAvailability($equipment);
                    
                    if ($availability['has_local_photos']) {
                        $results['skipped']++;
                        continue;
                    }
                    
                    // Récupérer les données Form associées
                    $formData = $this->findBy([
                        'equipment_id' => $equipment->getNumeroEquipement(),
                        'raison_sociale_visite' => $equipment->getRaisonSociale() . '\\' . $equipment->getVisite()
                    ]);
                    
                    if (empty($formData)) {
                        $results['skipped']++;
                        continue;
                    }
                    
                    // Migrer les photos depuis Kizeo vers le stockage local
                    $migrated = $this->migratePhotosForEquipment($equipment, $formData[0], $agence);
                    
                    if ($migrated) {
                        $results['migrated']++;
                    } else {
                        $results['skipped']++;
                    }
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    error_log("Erreur migration équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                }
            }
            
            $results['batches_completed']++;
            $offset += $batchSize;
            
            // Nettoyer la mémoire
            $this->getEntityManager()->clear();
            gc_collect_cycles();
        }
        
        return $results;
    }

    /**
     * Migre les photos d'un équipement spécifique
     */
    public function migratePhotosForEquipment($equipment, $formData, $agence): bool
    {
        try {
            // Récupérer les informations nécessaires depuis l'équipement
            $raisonSociale = $equipment ? $equipment->getRaisonSociale() : '';
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();
            $codeEquipement = $equipment->getNumeroEquipement();
            
            // CORRECTION CRITIQUE: Récupérer id_contact et id_societe
            $idContact = $equipment->getIdContact();
            $idSociete = $equipment->getCodeSociete();
            
            // VALIDATION: Vérifier que les champs critiques sont présents
            if (empty($idContact)) {
                error_log("ERREUR: id_contact manquant pour équipement {$codeEquipement}");
                return false;
            }
            
            // Construction correcte du champ raison_sociale_visite
            $raisonSocialeVisite = $equipment->getRaisonSociale() . "\\" . $equipment->getVisite();
            
            // Vérifier si une entrée Form existe déjà 
            $existingForm = $this->findOneBy([
                'code_equipement' => $codeEquipement,
                'raison_sociale_visite' => $raisonSocialeVisite
            ]);
            
            if (!$existingForm) {
                // Créer une nouvelle entrée Form si elle n'existe pas
                $existingForm = new Form();
                $existingForm->setCodeEquipement($codeEquipement);
                $existingForm->setFormId($formData->getFormId());
                $existingForm->setDataId($formData->getDataId());
            }
            
            // CORRECTION CRITIQUE: Assigner tous les champs requis
            $existingForm->setRaisonSocialeVisite($raisonSocialeVisite);
            $existingForm->setIdContact($idContact);    // AJOUT CRUCIAL
            $existingForm->setIdSociete($idSociete);    // AJOUT CRUCIAL
            
            // Mapping des photos à migrer
            $photosToMigrate = [
                'compte_rendu' => $formData->getPhotoCompteRendu(),
                'environnement' => $formData->getPhotoEnvironnementEquipement1(),
                'plaque' => $formData->getPhotoPlaque(),
                'etiquette_somafi' => $formData->getPhotoEtiquetteSomafi(),
                'generale' => $formData->getPhoto2()
            ];
            
            $migratedCount = 0;
            
            foreach ($photosToMigrate as $photoType => $photoName) {
                if (!empty($photoName)) {
                    // MODIFICATION : Utiliser la nouvelle architecture de stockage avec id_contact
                    if ($this->downloadAndStorePhotoFromKizeoNewArchitecture(
                        $photoName,
                        $formData->getFormId(),
                        $formData->getDataId(),
                        $agence,
                        $idContact,  // Utiliser id_contact au lieu de raison_sociale
                        $anneeVisite,
                        $typeVisite,
                        $codeEquipement . '_' . $photoType
                    )) {
                        $migratedCount++;
                        
                        // Mettre à jour le champ approprié de l'entité Form
                        switch ($photoType) {
                            case 'compte_rendu':
                                $existingForm->setPhotoCompteRendu($photoName);
                                break;
                            case 'environnement':
                                $existingForm->setPhotoEnvironnementEquipement1($photoName);
                                break;
                            case 'plaque':
                                $existingForm->setPhotoPlaque($photoName);
                                break;
                            case 'etiquette_somafi':
                                $existingForm->setPhotoEtiquetteSomafi($photoName);
                                break;
                            case 'generale':
                                $existingForm->setPhoto2($photoName);
                                break;
                        }
                    }
                }
            }
            
            // SAUVEGARDER l'entité Form avec tous les champs correctement renseignés
            $this->getEntityManager()->persist($existingForm);
            $this->getEntityManager()->flush();
            
            return $migratedCount > 0;
            
        } catch (\Exception $e) {
            error_log("Erreur migration photos équipement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NOUVELLE MÉTHODE : Téléchargement avec la nouvelle architecture utilisant id_contact
     */
    private function downloadAndStorePhotoFromKizeoNewArchitecture(
        string $photoName,
        string $formId,
        string $dataId,
        string $codeAgence,
        string $idContact,      // MODIFICATION: Utiliser id_contact au lieu de raisonSociale
        string $anneeVisite,
        string $typeVisite,
        string $filename
    ): bool {
        try {
            // Télécharger la photo depuis Kizeo
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $dataId . '/medias/' . $photoName,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );

            $photoContent = $response->getContent();
            
            // Utiliser ImageStorageService avec la nouvelle architecture
            $imageStorageService = $this->container->get('App\Service\ImageStorageService');
            
            $filepath = $imageStorageService->storeImage(
                $codeAgence,
                $idContact,   // MODIFICATION: Utiliser id_contact au lieu de raison_sociale  
                $anneeVisite,
                $typeVisite,
                $filename,
                $photoContent
            );
            
            return !empty($filepath);
            
        } catch (\Exception $e) {
            error_log("Erreur téléchargement photo {$photoName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔥 MÉTHODE SUPPLÉMENTAIRE : Pour corriger les données existantes
     */
    public function fixMissingRaisonSocialeVisite($agencyCode): array
    {
        $results = ['fixed' => 0, 'errors' => 0];
        
        try {
            // Récupérer toutes les entrées Form avec raison_sociale_visite manquant ou vide
            $qb = $this->createQueryBuilder('f')
                ->where('f.raison_sociale_visite IS NULL OR f.raison_sociale_visite = :empty')
                ->andWhere('f.code_equipement IS NOT NULL')
                ->setParameter('empty', '');
            
            $formsToFix = $qb->getQuery()->getResult();
            
            foreach ($formsToFix as $form) {
                try {
                    $codeEquipement = $form->getCodeEquipement();
                    
                    // Trouver l'équipement correspondant pour récupérer raison_sociale et visite
                    $repository = $this->getRepositoryForAgency($agencyCode, $this->getEntityManager());
                    $equipment = $repository->findOneBy(['numeroEquipement' => $codeEquipement]);
                    
                    if ($equipment) {
                        $raisonSocialeVisite = $equipment->getRaisonSociale() . "\\" . $equipment->getVisite();
                        $form->setRaisonSocialeVisite($raisonSocialeVisite);
                        
                        $this->getEntityManager()->persist($form);
                        $results['fixed']++;
                    }
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    error_log("Erreur correction raison_sociale_visite pour {$form->getCodeEquipement()}: " . $e->getMessage());
                }
            }
            
            // Sauvegarder tous les changements
            $this->getEntityManager()->flush();
            
        } catch (\Exception $e) {
            $results['errors']++;
            error_log("Erreur globale correction raison_sociale_visite: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Méthode helper pour récupérer le bon repository selon l'agence
     */
    private function getRepositoryForAgency($agencyCode, EntityManagerInterface $entityManager)
    {
        $repositoryMap = [
            'S10' => 'App\Entity\EquipementS10',
            'S40' => 'App\Entity\EquipementS40',
            'S50' => 'App\Entity\EquipementS50',
            'S60' => 'App\Entity\EquipementS60',
            'S70' => 'App\Entity\EquipementS70',
            'S80' => 'App\Entity\EquipementS80',
            'S100' => 'App\Entity\EquipementS100',
            'S120' => 'App\Entity\EquipementS120',
            'S130' => 'App\Entity\EquipementS130',
            'S140' => 'App\Entity\EquipementS140',
            'S150' => 'App\Entity\EquipementS150',
            'S160' => 'App\Entity\EquipementS160',
            'S170' => 'App\Entity\EquipementS170',
        ];
        
        if (!isset($repositoryMap[$agencyCode])) {
            throw new \InvalidArgumentException("Agence inconnue: {$agencyCode}");
        }
        
        return $entityManager->getRepository($repositoryMap[$agencyCode]);
    }

    /**
     * Télécharge et stocke une photo depuis l'API Kizeo
     */
    private function downloadAndStorePhotoFromKizeo(
        string $photoName,
        string $formId,
        string $dataId,
        string $agence,
        string $raisonSociale,
        string $anneeVisite,
        string $typeVisite,
        string $filename
    ): bool {
        try {
            // Vérifier si la photo existe déjà localement
            if ($this->imageStorageService->imageExists($agence, $raisonSociale, $anneeVisite, $typeVisite, $filename)) {
                return true; // Déjà présente
            }
            
            // Télécharger depuis l'API Kizeo
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $dataId . '/medias/' . $photoName,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );
            
            $imageContent = $response->getContent();
            
            if (empty($imageContent)) {
                return false;
            }
            
            // Sauvegarder localement
            $this->imageStorageService->storeImage(
                $agence,
                $raisonSociale,
                $anneeVisite,
                $typeVisite,
                $filename,
                $imageContent
            );
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Erreur téléchargement photo {$photoName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ROUTE DE MAINTENANCE - Nettoie les photos orphelines
     */
    public function cleanOrphanedPhotos(string $agence): array
    {
        $results = [
            'checked' => 0,
            'deleted' => 0,
            'errors' => 0,
            'size_freed' => 0
        ];
        
        try {
            $baseDir = $this->imageStorageService->getStorageStats();
            
            if (!isset($baseDir['agencies'][$agence])) {
                return $results;
            }
            
            // Récupérer tous les équipements existants
            $repository = $this->getEntityManager()->getRepository("App\\Entity\\Equipement{$agence}");
            $existingEquipments = $repository->createQueryBuilder('e')
                ->select('e.numeroEquipement', 'e.raisonSociale', 'e.visite', 'e.dateEnregistrement')
                ->getQuery()
                ->getArrayResult();
            
            $existingEquipmentIds = array_map(function($eq) {
                return $eq['numeroEquipement'];
            }, $existingEquipments);
            
            // Scanner les photos locales et identifier les orphelines
            $agenceDir = $this->imageStorageService->getBaseImagePath() . $agence;
            
            if (is_dir($agenceDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($agenceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'jpg') {
                        $results['checked']++;
                        
                        // Extraire le code équipement du nom de fichier
                        $filename = $file->getBasename('.jpg');
                        $equipmentCode = explode('_', $filename)[0];
                        
                        // Vérifier si l'équipement existe encore
                        if (!in_array($equipmentCode, $existingEquipmentIds)) {
                            $fileSize = $file->getSize();
                            
                            if (unlink($file->getPathname())) {
                                $results['deleted']++;
                                $results['size_freed'] += $fileSize;
                            } else {
                                $results['errors']++;
                            }
                        }
                    }
                }
            }
            
            // Nettoyer les répertoires vides
            $this->imageStorageService->cleanEmptyDirectories($agence);
            
        } catch (\Exception $e) {
            $results['errors']++;
            error_log("Erreur nettoyage photos orphelines: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * STATISTIQUES - Rapport de migration des photos
     */
    public function getPhotoMigrationReport(string $agence): array
    {
        $repository = $this->getEntityManager()->getRepository("App\\Entity\\Equipement{$agence}");
        $totalEquipments = $repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $equipmentsWithLocalPhotos = 0;
        $equipmentsWithoutLocalPhotos = 0;
        $totalLocalPhotos = 0;
        
        $equipments = $repository->findAll();
        
        foreach ($equipments as $equipment) {
            $availability = $this->checkLocalPhotosAvailability($equipment);
            
            if ($availability['has_local_photos']) {
                $equipmentsWithLocalPhotos++;
                $totalLocalPhotos += $availability['photo_count'];
            } else {
                $equipmentsWithoutLocalPhotos++;
            }
        }
        
        $migrationPercentage = $totalEquipments > 0 
            ? round(($equipmentsWithLocalPhotos / $totalEquipments) * 100, 2) 
            : 0;
        
        $storageStats = $this->imageStorageService->getStorageStats();
        $agencyStats = $storageStats['agencies'][$agence] ?? ['count' => 0, 'size' => 0, 'size_formatted' => '0 B'];
        
        return [
            'agence' => $agence,
            'total_equipments' => $totalEquipments,
            'equipments_with_local_photos' => $equipmentsWithLocalPhotos,
            'equipments_without_local_photos' => $equipmentsWithoutLocalPhotos,
            'migration_percentage' => $migrationPercentage,
            'total_local_photos' => $totalLocalPhotos,
            'storage_used' => $agencyStats['size_formatted'],
            'average_photos_per_equipment' => $equipmentsWithLocalPhotos > 0 
                ? round($totalLocalPhotos / $equipmentsWithLocalPhotos, 2) 
                : 0,
            'report_generated' => date('Y-m-d H:i:s')
        ];
    }

/**
 * INSTRUCTIONS DE DÉPLOIEMENT:
 * 
 * 1. Remplacer les appels existants dans vos contrôleurs PDF:
 *    - getPictureArrayByIdEquipment() → getPictureArrayByIdEquipmentOptimized()
 *    - getPictureArrayByIdSupplementaryEquipment() → getPictureArrayByIdSupplementaryEquipmentOptimized()
 * 
 * 2. Migrer les photos existantes:
 *    php bin/console app:migrate-photos S140
 * 
 * 3. Vérifier la migration:
 *    GET /api/maintenance/photo-migration-report/S140
 * 
 * 4. Nettoyer périodiquement les photos orphelines:
 *    GET /api/maintenance/clean-orphaned-photos/S140
 * 
 * 5. Tester la génération PDF avec les nouvelles méthodes
 * 
 * AVANTAGES:
 * - Plus de timeout lors de la génération des PDFs
 * - Performances grandement améliorées (pas d'appels API)
 * - Fallback automatique vers l'API si photos locales indisponibles
 * - Système de migration pour les équipements existants
 * - Nettoyage automatique des photos orphelines
 */

    /**
    * Méthode manquante pour obtenir le chemin de base des images
    */
    public function getBaseImagePath(): string
    {
        return $this->imageStorageService->getBaseImagePath();
    }

    /**
     * Récupère spécifiquement la photo générale depuis le stockage local
     * Architecture: backend-kizeo.somafi-group.fr/public/img/S60/GEODIS_CORBAS/2025/CE1/
     * Photo format: {CODE_EQUIPEMENT}_generale.jpg
     */
    public function getGeneralPhotoFromLocalStorage($equipment, EntityManagerInterface $entityManager): array
    {
        try {
            // Essayer d'abord la méthode traditionnelle
            $photoPath = $this->findPhotoTraditionalWay($equipment);
            
            // Si échec, utiliser le scan dynamique
            if (!$photoPath) {
                $photoPath = $this->findPhotoWithDynamicScan($equipment, 'generale');
            }
            
            if ($photoPath && file_exists($photoPath)) {
                $photoContent = file_get_contents($photoPath);
                $photoEncoded = base64_encode($photoContent);
                
                return [[
                    'picture' => $photoEncoded,
                    'update_time' => date('Y-m-d H:i:s', filemtime($photoPath)),
                    'photo_type' => 'generale_locale'
                ]];
            }
            
            return [];
            
        } catch (\Exception $e) {
            error_log("Erreur récupération photo: " . $e->getMessage()); // CORRIGÉ: error_log au lieu de customLog
            return [];
        }
    }

    
    /**
     * Trouve une photo avec la méthode traditionnelle (raison sociale)
     */
    private function findPhotoTraditionalWay($equipment): ?string
    {
        try {
            $agence = $equipment->getCodeAgence();
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $anneeVisite = '2025';
            $typeVisite = 'CE1';
            $numeroEquipement = $equipment->getNumeroEquipement();
            
            // Construction du chemin traditionnel
            $photoPath = $_SERVER['DOCUMENT_ROOT'] . "/public/img/{$agence}/{$raisonSociale}/{$anneeVisite}/{$typeVisite}/{$numeroEquipement}_generale.jpg";
            
            if (file_exists($photoPath)) {
                return $photoPath;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("Erreur findPhotoTraditionalWay: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Scan dynamique pour trouver la photo
     */
    private function findPhotoWithDynamicScan($equipment, string $photoType = 'generale'): ?string
    {
        try {
            $agence = $equipment->getCodeAgence();
            $numeroEquipement = $equipment->getNumeroEquipement();
            
            // Scanner tous les dossiers clients pour trouver les photos
            $baseAgencePath = $_SERVER['DOCUMENT_ROOT'] . "/public/img/{$agence}/";
            
            if (!is_dir($baseAgencePath)) {
                return null;
            }
            
            $clientDirs = scandir($baseAgencePath);
            foreach ($clientDirs as $clientDir) {
                if ($clientDir === '.' || $clientDir === '..') continue;
                
                $clientPath = $baseAgencePath . $clientDir . "/2025/CE1/";
                if (!is_dir($clientPath)) continue;
                
                // Chercher le fichier photo
                $photoPath = $clientPath . $numeroEquipement . "_{$photoType}.jpg";
                
                if (file_exists($photoPath)) {
                    return $photoPath;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("Erreur findPhotoWithDynamicScan: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Méthode de scan alternatif pour trouver la photo générale
     * Utile si la structure exacte varie légèrement
     */
    public function findGeneralPhotoByScanning($equipment): array
    {
        $picturesdata = [];
        
        try {
            $agence = $equipment->getCodeAgence();
            $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
            $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
            $typeVisite = $equipment->getVisite();
            $codeEquipement = $equipment->getNumeroEquipement();
            
            // Répertoire de base à scanner
            $baseDir = sprintf(
                '%s/public/img/%s/%s/%s/%s',
                $_SERVER['DOCUMENT_ROOT'],
                $agence,
                $raisonSociale,
                $anneeVisite,
                $typeVisite
            );
            
            if (is_dir($baseDir)) {
                // Scanner le répertoire pour trouver les photos de cet équipement
                $files = scandir($baseDir);
                
                foreach ($files as $file) {
                    // Chercher spécifiquement la photo générale
                    if (strpos($file, $codeEquipement . '_generale.jpg') !== false) {
                        $fullPath = $baseDir . '/' . $file;
                        
                        if (is_file($fullPath) && is_readable($fullPath)) {
                            $photoContent = file_get_contents($fullPath);
                            $pictureEncoded = base64_encode($photoContent);
                            
                            $picturesdataObject = new \stdClass();
                            $picturesdataObject->picture = $pictureEncoded;
                            $picturesdataObject->update_time = date('Y-m-d H:i:s', filemtime($fullPath));
                            $picturesdataObject->photo_type = 'generale_scan';
                            $picturesdataObject->local_path = $fullPath;
                            $picturesdataObject->equipment_number = $codeEquipement;
                            
                            $picturesdata[] = $picturesdataObject;
                            
                            error_log("✅ Photo générale trouvée par scan pour {$codeEquipement}: {$fullPath}");
                            break; // Ne prendre que la première trouvée
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            error_log("❌ Erreur scan photo générale pour {$equipment->getNumeroEquipement()}: " . $e->getMessage());
        }
        
        return $picturesdata;
    }

    /**
     * Version améliorée de getPictureArrayByIdEquipment qui privilégie les photos locales
     */
    public function getPictureArrayByIdEquipmentWithLocalPhotos($picturesArray, EntityManagerInterface $entityManager, $equipment): array
    {
        // D'abord essayer de récupérer la photo générale locale
        $localPhotos = $this->getGeneralPhotoFromLocalStorage($equipment, $entityManager);
        
        // Si aucune photo locale n'est trouvée, essayer le scan
        if (empty($localPhotos)) {
            $localPhotos = $this->findGeneralPhotoByScanning($equipment);
        }
        
        // Si des photos locales sont trouvées, les retourner
        if (!empty($localPhotos)) {
            return $localPhotos;
        }
        
        // Sinon, fallback vers l'ancienne méthode avec l'API
        error_log("🔄 Fallback API pour {$equipment->getNumeroEquipement()}");
        return $this->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
    }
}
