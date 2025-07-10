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

/**
 * @extends ServiceEntityRepository<ApiForm>
 */
class FormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  public HttpClientInterface $client)
    {
        parent::__construct($registry, Form::class);
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
    * @return Form[] Returns an array of forms from Kizeo
    */
    // public function getFormsMaintenance($cache): array 
    // {
    //     // Cache pour les formulaires MAINTENANCE
    //     $formsCacheKey = 'maintenance_forms_list';
    //     $cachedForms = $cache->get($formsCacheKey, function(ItemInterface $item) {
    //         $item->expiresAfter(3600); // Cache valide 1 heure
            
    //         $response = $this->client->request(
    //             'GET',
    //             'https://forms.kizeo.com/rest/v3/forms', [
    //                 'headers' => [
    //                     'Accept' => 'application/json',
    //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //                 ],
    //             ]
    //         );
    //         $content = $response->toArray();
    
    //         return array_filter($content['forms'], function($form) {
    //             return $form['class'] == "MAINTENANCE";
    //         });
    //     });
    
    //     $formMaintenanceArrayOfObject = [];
    //     $allFormsIds = array_column($cachedForms, 'id');
        
    //     $cachedFormData = [];
    //     // Cache pour chaque formulaire
    //     foreach ($allFormsIds as $formId) {
    //         // Clé de cache unique pour chaque formulaire
    //         $dataCacheKey = 'maintenance_form_data_' . $formId;
    //         $cachedFormData = $cache->get($dataCacheKey, function(ItemInterface $item) use ($formId) {
    //             $item->expiresAfter(1800); // Cache valide 30 minutes
                
    //             $response = $this->client->request('POST', 
    //                 'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', [
    //                     'headers' => [
    //                         'Accept' => 'application/json',
    //                         'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //                     ],
    //                 ]
    //             );
    //             $content = $response->getContent();
    //             $content = $response->toArray();
    //             return $content['data'];
    //         });
    //     }

    //     // Mettre en cache avec expiration (par exemple 24 heures)
    //     $formattedData = $cache->get('all_form_id_with_their_data_id', function (ItemInterface $item) use ($cachedFormData) {
    //         $item->expiresAfter(3600); // 1 heure

    //         $formattedData = [];
    //         foreach ($cachedFormData as $item) {
    //             $formId = $item['_form_id'];
    //             $dataId = intval($item['_id']); // Convertir à int

    //             if (!isset($formattedData[$formId])) {
    //                 $formattedData[$formId] = [];
    //             }

    //             $formattedData[$formId][] = $dataId;
    //         }

    //         return $formattedData;
    //     });
    //     // dd($formattedData);
    //     // array:1 [▼
    //     //     1034808 => array:5 [▼
    //     //         0 => "212851512"
    //     //         1 => "213145512"
    //     //         2 => "213435284"
    //     //         3 => "213762192"
    //     //         4 => "213933129"
    //     //     ]
    //     // ]
    //     foreach ($formattedData as $theFormId => $dataIds) {
    //         $idDesDatas = [];
    //         foreach ($dataIds as $dataId) {
    //             $idDesDatas[] = intval($dataId); // Convertir à int
    //         }
    //         // Effectuer une action de marquage de tous les formulaires en une seule requête
    //         $this->client->request('POST', 
    //             'https://forms.kizeo.com/rest/v3/forms/' . $theFormId . '/markasunreadbyaction/read', [
    //                 'headers' => [
    //                     'Accept' => 'application/json',
    //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //                 ],
    //                 'json' => [
    //                     "data_ids" => $idDesDatas
    //                 ]
    //             ]
    //         );  
    //     }
        
        
    //     return $formMaintenanceArrayOfObject;
    // }
        
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


    //      ----------------------------------------------------------------------------------------------------------------------
    //      ---------------------------------------- SAVE NEW EQUIPMENTS TO LOCAL BDD --------------------------------------
    //      ----------------------------------------------------------------------------------------------------------------------
    /**
     * Function to create and save new equipments in local database by agency --- OK POUR TOUTES LES AGENCES DE S10 à S170 --- MAJ IMAGES OK
     */
    public function createAndSaveInDatabaseByAgency($equipements, $entityAgency){
    
        /**
         * Première étape : Enregistrement des équipements AU CONTRAT
         */
        foreach ($equipements['contrat_de_maintenance']['value'] as $additionalEquipment){
            $equipement = new $entityAgency;
            $equipement->setIdContact($equipements['id_client_']['value']);
            $equipement->setRaisonSociale($equipements['nom_client']['value']);
            if (isset($equipements['test_']['value'])) {
                $equipement->setTest($equipements['test_']['value']);
            }
            $equipement->setDateEnregistrement($equipements['date_et_heure1']['value']);

            if (isset($equipements['id_societe']['value'])) {
                $equipement->setCodeSociete($equipements['id_societe']['value']);
            } else {
                $equipement->setCodeSociete("");
            }
            if (isset($equipements['id_agence']['value'])) {
                $equipement->setCodeAgence($equipements['id_agence']['value']);
            } else {
                $equipement->setCodeAgence("");
            }
            
            $equipement->setDerniereVisite($equipements['date_et_heure1']['value']);
            $equipement->setTrigrammeTech($equipements['trigramme']['value']);
            $equipement->setSignatureTech($equipements['signature3']['value']);
            
            // Détermination du type de visite
            if (str_contains($additionalEquipment['equipement']['path'], 'CE1')) {
                $equipement->setVisite("CE1");
            } elseif(str_contains($additionalEquipment['equipement']['path'], 'CE2')){
                $equipement->setVisite("CE2");
            } elseif(str_contains($additionalEquipment['equipement']['path'], 'CE3')){
                $equipement->setVisite("CE3");
            } elseif(str_contains($additionalEquipment['equipement']['path'], 'CE4')){
                $equipement->setVisite("CE4");
            } elseif(str_contains($additionalEquipment['equipement']['path'], 'CEA')){
                $equipement->setVisite("CEA");
            }
            
            $equipement->setNumeroEquipement($additionalEquipment['equipement']['value']);
            $equipement->setIfExistDB($additionalEquipment['equipement']['columns']);
            $equipement->setLibelleEquipement(strtolower($additionalEquipment['reference7']['value']));
            $equipement->setModeFonctionnement($additionalEquipment['mode_fonctionnement_2']['value']);
            $equipement->setRepereSiteClient($additionalEquipment['localisation_site_client']['value']);
            $equipement->setMiseEnService($additionalEquipment['reference2']['value']);
            $equipement->setNumeroDeSerie($additionalEquipment['reference6']['value']);
            $equipement->setMarque($additionalEquipment['reference5']['value']);
            
            if (isset($additionalEquipment['reference3']['value'])) {
                $equipement->setLargeur($additionalEquipment['reference3']['value']);
            } else {
                $equipement->setLargeur("");
            }
            if (isset($additionalEquipment['reference1']['value'])) {
                $equipement->setHauteur($additionalEquipment['reference1']['value']);
            } else {
                $equipement->setHauteur("");
            }
            if (isset($additionalEquipment['longueur']['value'])) {
                $equipement->setLongueur($additionalEquipment['longueur']['value']);
            } else {
                $equipement->setLongueur("NC");
            }
            
            $equipement->setPlaqueSignaletique($additionalEquipment['plaque_signaletique']['value']);
            $equipement->setEtat($additionalEquipment['etat']['value']);
            
            if (isset($additionalEquipment['hauteur_de_nacelle_necessaire']['value'])) {
                $equipement->setHauteurNacelle($additionalEquipment['hauteur_de_nacelle_necessaire']['value']);
            } else {
                $equipement->setHauteurNacelle("");
            }
            
            if (isset($additionalEquipment['si_location_preciser_le_model']['value'])) {
                $equipement->setModeleNacelle($additionalEquipment['si_location_preciser_le_model']['value']);
            } else {
                $equipement->setModeleNacelle("");
            }
            
            if (isset($additionalEquipment['etat']['value'])) {
                switch ($additionalEquipment['etat']['value']) {
                    case "Rien à signaler le jour de la visite. Fonctionnement ok":
                        $equipement->setStatutDeMaintenance("Vert");
                        break;
                    case "Travaux à prévoir":
                        $equipement->setStatutDeMaintenance("Orange");
                        break;
                    case "Travaux obligatoires":
                        $equipement->setStatutDeMaintenance("Rouge");
                        break;
                    case "Equipement inaccessible le jour de la visite":
                        $equipement->setStatutDeMaintenance("Inaccessible");
                        break;
                    case "Equipement à l'arrêt le jour de la visite":
                        $equipement->setStatutDeMaintenance("A l'arrêt");
                        break;
                    case "Equipement mis à l'arrêt lors de l'intervention":
                        $equipement->setStatutDeMaintenance("Rouge");
                        break;
                    case "Equipement non présent sur site":
                        $equipement->setStatutDeMaintenance("Non présent");
                        break;
                    default:
                        $equipement->setStatutDeMaintenance("NC");
                        break;
                }
            }

            $equipement->setEnMaintenance(true);
            $equipement->setIsArchive(false);
            
            $this->getEntityManager()->persist($equipement);
        }

        /**
         * Deuxième étape : Traitement des équipements HORS CONTRAT
         * avec attribution automatique des numéros
         */
        foreach ($equipements['tableau2']['value'] as $equipementsHorsContrat){
            $equipement = new $entityAgency;
            $equipement->setIdContact($equipements['id_client_']['value']);
            $equipement->setRaisonSociale($equipements['nom_client']['value']);
            if (isset($equipements['test_']['value'])) {
                $equipement->setTest($equipements['test_']['value']);
            }
            $equipement->setDateEnregistrement($equipements['date_et_heure1']['value']);

            if (isset($equipements['id_societe']['value'])) {
                $equipement->setCodeSociete($equipements['id_societe']['value']);
            } else {
                $equipement->setCodeSociete("");
            }
            if (isset($equipements['id_agence']['value'])) {
                $equipement->setCodeAgence($equipements['id_agence']['value']);
            } else {
                $equipement->setCodeAgence("");
            }
            
            $equipement->setDerniereVisite($equipements['date_et_heure1']['value']);
            $equipement->setTrigrammeTech($equipements['trigramme']['value']);
            $equipement->setSignatureTech($equipements['signature3']['value']);
            
            // Détermination du type de visite (basée sur le premier équipement au contrat)
            if (!empty($equipements['contrat_de_maintenance']['value'])) {
                if (str_contains($equipements['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE1')) {
                    $equipement->setVisite("CE1");
                } elseif(str_contains($equipements['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE2')){
                    $equipement->setVisite("CE2");
                } elseif(str_contains($equipements['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE3')){
                    $equipement->setVisite("CE3");
                } elseif(str_contains($equipements['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE4')){
                    $equipement->setVisite("CE4");
                } elseif(str_contains($equipements['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CEA')){
                    $equipement->setVisite("CEA");
                } else {
                    $equipement->setVisite("CE1"); // Valeur par défaut
                }
            } else {
                $equipement->setVisite("CE1"); // Valeur par défaut si pas d'équipement au contrat
            }
            
            // Attribution automatique du numéro d'équipement
            $typeLibelle = strtolower($equipementsHorsContrat['nature']['value']);
            $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
            
            // Interroger la base de données pour obtenir le prochain numéro
            $idClient = $equipements['id_client_']['value'];
            $nouveauNumero = $this->getNextEquipmentNumberFromDatabase($typeCode, $idClient, $entityAgency);
            
            // Formater le numéro d'équipement (ex: SEC01)
            $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
            $equipement->setNumeroEquipement($numeroFormate);
            
            $equipement->setLibelleEquipement($typeLibelle);
            $equipement->setModeFonctionnement($equipementsHorsContrat['mode_fonctionnement_']['value']);
            $equipement->setRepereSiteClient($equipementsHorsContrat['localisation_site_client1']['value']);
            $equipement->setMiseEnService($equipementsHorsContrat['annee']['value']);
            $equipement->setNumeroDeSerie($equipementsHorsContrat['n_de_serie']['value']);
            $equipement->setMarque($equipementsHorsContrat['marque']['value']);
            
            if (isset($equipementsHorsContrat['largeur']['value'])) {
                $equipement->setLargeur($equipementsHorsContrat['largeur']['value']);
            } else {
                $equipement->setLargeur("");
            }
            if (isset($equipementsHorsContrat['hauteur']['value'])) {
                $equipement->setHauteur($equipementsHorsContrat['hauteur']['value']);
            } else {
                $equipement->setHauteur("");
            }
            
            $equipement->setPlaqueSignaletique($equipementsHorsContrat['plaque_signaletique1']['value']);
            $equipement->setEtat($equipementsHorsContrat['etat1']['value']);
            
            if (isset($equipementsHorsContrat['etat1']['value'])) {
                switch ($equipementsHorsContrat['etat1']['value']) {
                    case "A":
                        $equipement->setStatutDeMaintenance("Bon état de fonctionnement le jour de la visite");
                        break;
                    case "B":
                        $equipement->setStatutDeMaintenance("Travaux préventifs");
                        break;
                    case "C":
                        $equipement->setStatutDeMaintenance("Travaux curatifs");
                        break;
                    case "D":
                        $equipement->setStatutDeMaintenance("Equipement à l'arrêt le jour de la visite");
                        break;
                    case "E":
                        $equipement->setStatutDeMaintenance("Equipement mis à l'arrêt lors de l'intervention");
                        break;
                    default:
                        $equipement->setStatutDeMaintenance("NC");
                        break;
                }
            }

            $equipement->setEnMaintenance(false);
            $equipement->setIsArchive(false);
            
            $this->getEntityManager()->persist($equipement);
        }
        
        // FLUSH ici seulement des équipement AU CONTRAT et HORS CONTRAT persistés
        $this->getEntityManager()->flush();
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


    /**      --------------------------------------------------------------------------------------------------------------------
    *      ---------------------------------------------  SAVE PDF STANDARD FROM KIZEO AND SAVE EQUIPMENTS IN BDD ---------------
    *      ----------------------------------------------------------------------------------------------------------------------
    *
    * Function to save PDF with pictures for maintenance equipements in directories on O2switch  -------------- LOCAL FUNCTIONNAL -------
    * Implementation du cache symfony pour améliorer la performance en remote
    * -------------------------------------- CALL BY 1ST CRON TASK
    */
    public function saveEquipmentsInDatabase($cache){
        // -----------------------------   Return all forms in an array | cached for 900 seconds 15 minutes
        $allFormsArray = $cache->get('all-forms-on-kizeo', function(ItemInterface $item){
            $item->expiresAfter(3600); // 1 hour
            $result = FormRepository::getForms();
            return $result['forms'];
        });

        $formMaintenanceUnread = [];
        $dataOfFormMaintenanceUnread = [];
        $allFormsKeyId = [];
        
        $entiteEquipementS10 = new EquipementS10;
        $entiteEquipementS40 = new EquipementS40;
        $entiteEquipementS50 = new EquipementS50;
        $entiteEquipementS60 = new EquipementS60;
        $entiteEquipementS70 = new EquipementS70;
        $entiteEquipementS80 = new EquipementS80;
        $entiteEquipementS100 = new EquipementS100;
        $entiteEquipementS120 = new EquipementS120;
        $entiteEquipementS130 = new EquipementS130;
        $entiteEquipementS140 = new EquipementS140;
        $entiteEquipementS150 = new EquipementS150;
        $entiteEquipementS160 = new EquipementS160;
        $entiteEquipementS170 = new EquipementS170;
        
        // ----------------------------- GET ALL ID from forms with class "MAINTENANCE"
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
                'https://forms.kizeo.com/rest/v3/forms/' .  $key . '/data/unread/lu/5', [
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
       
        // ----------------------------------------------------------------------- Début d'appel des data des formulaires non lus
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
                // array_push($dataOfFormMaintenanceUnread, $result['data']['fields']);
                array_push($dataOfFormMaintenanceUnread, $result);

                // Mark them as READ
                 // -------------------------------------------            MARK FORM AS READ !!!
                // ------------------------------------------------------------------------------
                $response = $this->client->request(
                    'POST',
                    'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/lu', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'json' => [
                            "data_ids" => [intval($form['_id'])]
                        ]
                    ]
                );
                // -------------------------------------------            MARKED FORM AS READ !!!
                // ------------------------------------------------------------------------------
            }
        }
        
        // ------------- Upload pictures equipements en BDD local
        foreach ($dataOfFormMaintenanceUnread as $equipements){
            $equipements = $equipements['data'];
            FormRepository::uploadPicturesInDatabase($equipements);
        }

        // ------------- Selon le code agence, enregistrement des equipements AU CONTRAT et HORS CONTRAT en BDD local
        foreach ($dataOfFormMaintenanceUnread as $equipements){
            $equipements = $equipements['data']['fields'];
            // ----------------------------------------------------------   
            // IF code_agence d'$equipement = S50 ou S100 ou etc... on boucle sur ses équipements supplémentaires
            // ----------------------------------------------------------
            switch ($equipements['code_agence']['value']) {
                // Passer à la fonction createAndSaveInDatabaseByAgency()
                // les variables $equipements avec les nouveaux équipements des formulaires de maintenance, le tableau des résumés de l'agence et son entité ex: $entiteEquipementS10
                case 'S10':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS10);
                    break;
                
                case 'S40':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS40);
                    break;
                
                case 'S50':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements,  $entiteEquipementS50);
                    break;
                
                
                case 'S60':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS60);
                    break;
                
                
                case 'S70':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS70);
                    break;
                
                
                case 'S80':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS80);
                    break;
                
                
                case 'S100':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements,  $entiteEquipementS100);
                    break;
                
                
                case 'S120':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS120);
                    break;
                
                
                case 'S130':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS130);
                    break;
                
                
                case 'S140':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS140);
                    break;
                
                
                case 'S150':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS150);
                    break;
                
                
                case 'S160':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS160);
                    break;
                
                
                case 'S170':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS170);
                    break;
                
                default:
                    break;
            }
        }
        
        return "L'enregistrement en base de données s'est bien déroulé";
    }


    /**
     * Function to mark maintenance forms as UNREAD 
     */
    // public function markMaintenanceFormsAsUnread($cache){
    //     // Récupérer les fichiers PDF dans un tableau
    //     // Filtrer uniquement les formulaires de maintenance
        
    //     $allFormsArray = $cache->get('forms_maintenance', function(ItemInterface $item) use ($cache){ // $allFormsData = $content['data'] from getFormsMaintenance()
    //         $item->expiresAfter(1800); // Cache pour 30 minutes
    //         $results = FormRepository::getFormsMaintenance($cache);
    //         return $results;
    //     });
    //     foreach ($allFormsArray as $data) {
    //         // Effectuer une action de marquage de tous les formulaires en une seule requête
    //         Request::enableHttpMethodParameterOverride(); // <-- add this line
    //         $this->client->request('POST', 
    //             'https://forms.kizeo.com/rest/v3/forms/' . $data->form_id . '/markasunreadbyaction/read', [
    //                 'headers' => [
    //                     'Accept' => 'application/json',
    //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //                 ],
    //                 'json' => [
    //                     "data_ids" => intval($data->data_id) // Convertir à int
    //                 ]
    //             ]
    //         );
    //     }

        
    // }

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
    /**
     * SAVE ALL PICTURES FROM FORMS MAINTENANCE IN FORM TABLE WITH THEIR ID_EQUIPMENT
     */
    public function savePicturesFromForms($cache){
        // -----------------------------   Return all forms in an array
        $allFormsArray = FormRepository::getForms();  // All forms on Kizeo
        $allFormsArray = $allFormsArray['forms'];
        $allFormsMaintenanceArray = []; // All forms with class "MAINTENANCE

        // ----------------------------- DÉBUT Return all forms with class "MAINTENANCE"
        foreach ($allFormsArray as $key => $value) {
            if ($allFormsArray[$key]['class'] === 'MAINTENANCE') {
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
                    array_push($allFormsMaintenanceArray, $value);
                }
            }
        }
        // -----------------------------  FIN Return all forms with class "MAINTENANCE"
        // dd($allFormsMaintenanceArray);
        $dataOfFormMaintenance = [];
        foreach ($allFormsMaintenanceArray as $form) {
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
            array_push($dataOfFormMaintenance, $result);
        }
        // ------------- Upload pictures equipements en BDD local
        foreach ($dataOfFormMaintenance as $equipements){
            $equipements = $equipements['data'];
            FormRepository::uploadPicturesInDatabase($equipements);
        }
        
        return "Les images sont bien enregistrées dans la table form";
    }

    public function uploadPicturesInDatabase($equipements){
        /**
        * List all additional equipments stored in individual array
        */

        // On sauvegarde les photos d'équipements AU CONTRAT issus des formulaires non lus en BDD
        foreach ($equipements['fields']['contrat_de_maintenance']['value']  as $additionalEquipment){
            $equipement = new Form;

            $equipement->setFormId($equipements['form_id']);
            $equipement->setDataId($equipements['id']);
            $equipement->setUpdateTime($equipements['update_time']);
            
            $equipement->setCodeEquipement($additionalEquipment['equipement']['value']);
            $equipement->setRaisonSocialeVisite($additionalEquipment['equipement']['path']);
            if (isset($additionalEquipment['photo_etiquette_somafi']['value'])) {
                $equipement->setPhotoEtiquetteSomafi($additionalEquipment['photo_etiquette_somafi']['value']);
            }
            $equipement->setPhotoPlaque($additionalEquipment['photo_plaque']['value']);
            $equipement->setPhotoChoc($additionalEquipment['photo_choc']['value']);
            if (isset($additionalEquipment['photo_choc_tablier_porte']['value'])) {
                $equipement->setPhotoChocTablierPorte($additionalEquipment['photo_choc_tablier_porte']['value']);
            }
            if (isset($additionalEquipment['photo_choc_tablier']['value'])) {
                $equipement->setPhotoChocTablier($additionalEquipment['photo_choc_tablier']['value']);
            }
            if (isset($additionalEquipment['photo_axe']['value'])) {
                $equipement->setPhotoAxe($additionalEquipment['photo_axe']['value']);
            }
            if (isset($additionalEquipment['photo_serrure']['value'])) {
                $equipement->setPhotoSerrure($additionalEquipment['photo_serrure']['value']);
            }
            if (isset($additionalEquipment['photo_serrure1']['value'])) {
                $equipement->setPhotoSerrure1($additionalEquipment['photo_serrure1']['value']);
            }
            if (isset($additionalEquipment['photo_feux']['value'])) {
                $equipement->setPhotoSerrure1($additionalEquipment['photo_feux']['value']);
            }
            $equipement->setPhotoPanneauIntermediaireI($additionalEquipment['photo_panneau_intermediaire_i']['value']);
            $equipement->setPhotoPanneauBasInterExt($additionalEquipment['photo_panneau_bas_inter_ext']['value']);
            $equipement->setPhotoLameBasseIntExt($additionalEquipment['photo_lame_basse_int_ext']['value']);
            $equipement->setPhotoLameIntermediaireInt($additionalEquipment['photo_lame_intermediaire_int_']['value']);
            $equipement->setPhotoEnvironnementEquipement1($additionalEquipment['photo_environnement_equipemen1']['value']);
            $equipement->setPhotoCoffretDeCommande($additionalEquipment['photo_coffret_de_commande']['value']);
            $equipement->setPhotoCarte($additionalEquipment['photo_carte']['value']);
            $equipement->setPhotoRail($additionalEquipment['photo_rail']['value']);
            $equipement->setPhotoEquerreRail($additionalEquipment['photo_equerre_rail']['value']);
            $equipement->setPhotoFixationCoulisse($additionalEquipment['photo_fixation_coulisse']['value']);
            $equipement->setPhotoMoteur($additionalEquipment['photo_moteur']['value']);
            $equipement->setPhotoDeformationPlateau($additionalEquipment['photo_deformation_plateau']['value']);
            $equipement->setPhotoDeformationPlaque($additionalEquipment['photo_deformation_plaque']['value']);
            $equipement->setPhotoDeformationStructure($additionalEquipment['photo_deformation_structure']['value']);
            $equipement->setPhotoDeformationChassis($additionalEquipment['photo_deformation_chassis']['value']);
            $equipement->setPhotoDeformationLevre($additionalEquipment['photo_deformation_levre']['value']);
            $equipement->setPhotoFissureCordon($additionalEquipment['photo_fissure_cordon']['value']);
            $equipement->setPhotoJoue($additionalEquipment['photo_joue']['value']);
            $equipement->setPhotoButoir($additionalEquipment['photo_butoir']['value']);
            $equipement->setPhotoVantail($additionalEquipment['photo_vantail']['value']);
            $equipement->setPhotoLinteau($additionalEquipment['photo_linteau']['value']);
            $equipement->setPhotoMarquageAuSol2($additionalEquipment['photo_marquage_au_sol_']['value']);
            $equipement->setPhoto2($additionalEquipment['photo2']['value']);
            
            
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $this->getEntityManager()->persist($equipement);
        }
        // On sauvegarde les équipements HORS CONTRAT issus des formulaires non lus en BDD
        foreach ($equipements['fields']['tableau2']['value']  as $equipmentSupplementaire){
            $equipement = new Form;

            $equipement->setFormId($equipements['form_id']);
            $equipement->setDataId($equipements['id']);
            $equipement->setUpdateTime($equipements['update_time']);
            $equipement->setRaisonSocialeVisite($equipements['fields']['contrat_de_maintenance']['value']['equipement']['path']);
            $equipement->setPhotoCompteRendu($equipmentSupplementaire['photo3']['value']);
            
            
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $this->getEntityManager()->persist($equipement);
        }
        $this->getEntityManager()->flush();
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

    public function getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment){
        $picturesdata = [];
        $photoJpg ="";
        foreach ($picturesArray as $key => $value) {
            // if ($equipment->getRaisonSociale() . "\\" . $equipment->getVisite() === $value->raison_sociale_visite) {
                // On récupère la photo du compte rendu uniquement au lieu de toutes les photos
                // Changer dans le tableau des photos à récupérer de function getJpgPictureFromStringName() pour toutes les avoir
                $photoJpg = $entityManager->getRepository(Form::class)->getJpgPictureFromStringName($value, $entityManager); // It's an array now
                foreach ($photoJpg as $photo) {
                    $pictureEncoded = base64_encode($photo);
                    $picturesdataObject = new stdClass;
                    $picturesdataObject->picture = $pictureEncoded;
                    $picturesdataObject->update_time = $value->update_time;
                    array_push($picturesdata, $picturesdataObject);
                }
            // }
        }
        return $picturesdata;
    }

    // Nouvelle fonction dans FormRepository.php pour récupérer spécifiquement les photos des équipements supplémentaires

    /**
     * Récupère les photos spécifiquement pour les équipements supplémentaires
     * Les équipements supplémentaires utilisent le champ photo_compte_rendu
     */
    public function getPictureArrayByIdSupplementaryEquipment($entityManager, $equipment) {
        $picturesdata = [];
        
        // Pour les équipements supplémentaires, on utilise la même logique de filtrage
        // que les équipements au contrat pour garantir l'unicité et le respect du tri par visite
        $picturesArray = $entityManager->getRepository(Form::class)->findBy([
            'code_equipement' => $equipment->getNumeroEquipement(),
            'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
        ]);
        
        foreach ($picturesArray as $value) {
            // Vérifier si c'est bien le bon équipement et qu'il y a une photo_compte_rendu
            if ($value->getPhotoCompteRendu() && $value->getPhotoCompteRendu() !== '') {
                $photoJpg = $this->getJpgPictureFromPhotoCompteRendu($value, $entityManager);
                
                if (!empty($photoJpg)) {
                    foreach ($photoJpg as $photo) {
                        $pictureEncoded = base64_encode($photo);
                        $picturesdataObject = new stdClass;
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
}
