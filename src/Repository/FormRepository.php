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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;

/**
 * @extends ServiceEntityRepository<ApiForm>
 */
class FormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry,  private HttpClientInterface $client)
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
        * @return Form[] Returns an array of forms from Kizeo
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

    //      ----------------------------------------------------------------------------------------------------------------------
    //      ---------------------------------------- GET EQUIPMENTS LISTS FROM KIZEO --------------------------------------
    //      ----------------------------------------------------------------------------------------------------------------------
    /**
    //  * @return Form[] Returns an array with all items from all agencies equipments lists 
    //  */
    // public function getAgencyListClientsFromKizeoByListId($list_id): array
    // {
    //     $clients = 
    //     //  $response = $this->client->request(
    //     //      'GET',
    //     //      'https://forms.kizeo.com/rest/v3/lists/' . $list_id, [
    //     //          'headers' => [
    //     //              'Accept' => 'application/json',
    //     //              'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //     //          ],
    //     //      ]
    //     //  );
    //     //  $content = $response->getContent();
    //     //  $content = $response->toArray();
         
    //     //  $clientsSplittedArray = [];
    //     //  // $clientsArray = array_map(null, $content['list']['items']);
    //     //  $clientsArray = array_map(null, $content['list']['items']);
    //     //  /* On Kizeo, all lines look like that
    //     //  *  ATEIS\CEA\SEC01|Porte sectionnelle|MISE EN SERVICE|NUMERO DE SERIE|ISEA|HAUTEUR|LARGEUR|REPERE SITE CLIENT|361|361|S50
    //     //  *
    //     //  *  And I need to sending this : 
    //     //  *  "ATEIS:ATEIS\CEA:CEA\SEC01:SEC01|Porte sectionnelle:Porte sectionnelle|MISE EN SERVICE:MISE EN SERVICE|NUMERO DE SERIE:NUMERO DE SERIE|ISEA:ISEA|HAUTEUR:HAUTEUR|LARGEUR:LARGEUR|REPERE SITE CLIENT:REPERE SITE CLIENT|361:361|361:361|S50:S50"
    //     //  */ 
    //     //  for ($i=0; $i < count($clientsArray) ; $i++) {
    //     //      if (isset($clientsArray[$i]) && in_array($clientsArray[$i], $clientsSplittedArray) == false) {
    //     //          array_push($clientsSplittedArray, array_unique(preg_split("/[:|]/", $clientsArray[$i])));
    //     //      }
    //     //  }
    //      return $clientsArray;
    // }
    
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
         
         /* On Kizeo, all lines look like that
         *  ATEIS\CEA\SEC01|Porte sectionnelle|MISE EN SERVICE|NUMERO DE SERIE|ISEA|HAUTEUR|LARGEUR|REPERE SITE CLIENT|361|361|S50
         *
         *  And I need to sending this : 
         *  "ATEIS:ATEIS\CEA:CEA\SEC01:SEC01|Porte sectionnelle:Porte sectionnelle|MISE EN SERVICE:MISE EN SERVICE|NUMERO DE SERIE:NUMERO DE SERIE|ISEA:ISEA|HAUTEUR:HAUTEUR|LARGEUR:LARGEUR|REPERE SITE CLIENT:REPERE SITE CLIENT|361:361|361:361|S50:S50"
         */ 
        //  for ($i=0; $i < count($equipementsArray) ; $i++) {
        //      if (isset($equipementsArray[$i]) && in_array($equipementsArray[$i], $equipementsSplittedArray) == false) {
        //          array_push($equipementsSplittedArray, preg_split("/[|]/", $equipementsArray[$i]));
        //      }
        //  }
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
                // $allFormsMaintenanceArray = $cache->get('allFormsMaintenanceArray', function(ItemInterface $item) use ($allFormsArray, $key, $allFormsMaintenanceArray) {
                //     $item->expiresAfter(604800); // 1 week

                //     $response = $this->client->request(
                //         'POST',
                //         'https://forms.kizeo.com/rest/v3/forms/' . $allFormsArray[$key]['id'] . '/data/advanced', [
                //             'headers' => [
                //                 'Accept' => 'application/json',
                //                 'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                //             ],
                //         ]
                //     );
                //     $content = $response->getContent();
                //     $content = $response->toArray();
                    
                //     foreach ($content['data'] as $key => $value) {
                //         array_push($allFormsMaintenanceArray, $value);
                //     }
                    
                // });
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
        * List all additional equipments stored in individual array
        */
        // On sauvegarde les équipements issus des formulaires non lus en BDD
        foreach ($equipements['contrat_de_maintenance']['value']  as $additionalEquipment){
            // Everytime a new resume is read, we store its value in variable resume_equipement_supplementaire
            // $resume_equipement_supplementaire = array_unique(preg_split("/[:|]/", $additionalEquipment['equipement']['columns']));
            // $resume_equipement_supplementaire = $additionalEquipment['equipement']['columns'];
            // dump($resume_equipement_supplementaire);
            /**
             * If resume_equipement_supplementaire value is NOT in  $allEquipementsResumeInDatabase array
             * Method used : in_array(search, inThisArray, type) 
             * type Optional. If this parameter is set to TRUE, the in_array() function searches for the search-string and specific type in the array
             */
            
            // if (!in_array($resume_equipement_supplementaire, $allEquipementsResumeInDatabase, TRUE) && $equipements['test_']['value'] != 'oui' ) {
            // As we are processing only unread forms, we don't need resumes in database and also resumes of equipements supplémentaires    
            // if (!in_array($resume_equipement_supplementaire, $arrayResumesEquipments, TRUE)) {
            /**
             * Persist each equipement in database
             * Save a new contrat_de_maintenance equipement in database when a technician make an update
             */
            $equipement = new $entityAgency;
            $equipement->setIdContact($equipements['id_client_']['value']);
            $equipement->setRaisonSociale($equipements['nom_client']['value']);
            if (isset($equipements['test_']['value'])) {
                $equipement->setTest($equipements['test_']['value']);
            }
            $equipement->setDateEnregistrement($equipements['date_et_heure1']['value']);

            if (isset($equipements['id_societe']['value'])) {
                $equipement->setCodeSociete($equipements['id_societe']['value']);
            }else{
                $equipement->setCodeSociete("");
            }
            if (isset($equipements['id_agence']['value'])) {
                $equipement->setCodeAgence($equipements['id_agence']['value']);
            }else{
                $equipement->setCodeAgence("");
            }
            
            $equipement->setDerniereVisite($equipements['date_et_heure1']['value']);
            $equipement->setTrigrammeTech($equipements['trigramme']['value']);
            $equipement->setSignatureTech($equipements['signature3']['value']);
            
            if (str_contains($additionalEquipment['equipement']['path'], 'CE1')) {
                $equipement->setVisite("CE1");
            }
            elseif(str_contains($additionalEquipment['equipement']['path'], 'CE2')){
                $equipement->setVisite("CE2");
            }
            elseif(str_contains($additionalEquipment['equipement']['path'], 'CE3')){
                $equipement->setVisite("CE3");
            }
            elseif(str_contains($additionalEquipment['equipement']['path'], 'CE4')){
                $equipement->setVisite("CE4");
            }
            elseif(str_contains($additionalEquipment['equipement']['path'], 'CEA')){
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
            }else{
                $equipement->setLargeur("");
            }
            if (isset($additionalEquipment['reference1']['value'])) {
                $equipement->setHauteur($additionalEquipment['reference1']['value']);
            }else{
                $equipement->setHauteur("");
            }
            if (isset($additionalEquipment['longueur']['value'])) {
                $equipement->setLongueur($additionalEquipment['longueur']['value']);
            }else{
                $equipement->setLongueur("NC");
            }
            $equipement->setPlaqueSignaletique($additionalEquipment['plaque_signaletique']['value']);
            // dd($additionalEquipment);
            // //Anomalies en fonction du libellé de l'équipement
            // switch($additionalEquipment['anomalie']['value']){
            //     case 'niveleur':
            //         $equipement->setAnomalies($additionalEquipment['anomalie_niveleur']['value']);
            //         break;
            //     case 'portail':
            //         $equipement->setAnomalies($additionalEquipment['anomalie_portail']['value']);
            //         break;
            //     case 'porte rapide':
            //         $equipement->setAnomalies($additionalEquipment['anomalie_porte_rapide']['value']);
            //         break;
            //     case 'porte pietonne':
            //         $equipement->setAnomalies($additionalEquipment['anomalie_porte_pietonne']['value']);
            //         break;
            //     case 'barriere':
            //         $equipement->setAnomalies($additionalEquipment['anomalie_barriere']['value']);
            //         break;
            //     case 'rideau':
            //         $equipement->setAnomalies($additionalEquipment['rid']['value']);
            //         break;
            //     default:
            //         $equipement->setAnomalies($additionalEquipment['anomalie']['value']);
                
            // }
            $equipement->setEtat($additionalEquipment['etat']['value']);
            if (isset($additionalEquipment['hauteur_de_nacelle_necessaire']['value'])) {
                $equipement->setHauteurNacelle($additionalEquipment['hauteur_de_nacelle_necessaire']['value']);
            }else{
                $equipement->setHauteurNacelle("");
            }
            
            if (isset($additionalEquipment['si_location_preciser_le_model']['value'])) {
                $equipement->setModeleNacelle($additionalEquipment['si_location_preciser_le_model']['value']);
            }else{
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
            
            dump("Les équipements de " . $equipements['nom_client']['value'] . " ont été sauvegardés en BDD");
            
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $this->getEntityManager()->persist($equipement);
            // actually executes the queries (i.e. the INSERT query)
            $this->getEntityManager()->flush();
            
            // }
        }
        // Mettre la fonction SAVE PDF
        // FormRepository::exportAndSavePdfInRootFolder($formUnread, $dataOfFormMaintenanceUnread);
        
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
            // dd($theEquipment);
            if (in_array($equipment['equipement']['path'], $agencyEquipments, true)) {
                $keyEquipment = array_search($equipment['equipement']['path'], $agencyEquipments);
                unset($agencyEquipments[$keyEquipment]);
                array_push($agencyEquipments, $theEquipment);
            }
        }
        dump(count($agencyEquipments));  // Sans le if on a 5797 équipements sinon avec le if on reste à 5710 équipements
        // J'enlève les doublons de la liste des equipements kizeo dans le tableau $agencyEquipments
        $arrayEquipmentsToPutToKizeo = array_unique($agencyEquipments); // array_unique n'enlève aucun équipement de la liste
        dump(count($arrayEquipmentsToPutToKizeo));

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
     * Function to upload and save list agency with new records from ETAT DES LIEUX PORTAILS formulaires to Kizeo --- OK POUR TOUTES LES AGENCES DE S10 à S170
     */
    // public function uploadListAgencyEtatDesLieuxPortailsWithNewRecordsOnKizeo($dataOfFormList, $key, $agencyEquipments, $agencyListId){
    //     // Mettre à jour pour les nouveaux portails des états des lieux
    //     foreach ($dataOfFormList[$key]['contrat_de_maintenance']['value'] as $equipment) {
    //         // Recréer le path avec pour modèle celui de la liste portails
    //         $theEquipment = $equipment['equipement']['path'] . "\\" . $equipment['equipement']['columns'];
    //         if (!in_array($theEquipment, $agencyEquipments, true)) {
    //             array_push($agencyEquipments,  $theEquipment);
    //         }
    //     }
    //     Request::enableHttpMethodParameterOverride(); // <-- add this line
    //     $client = new Client();
    //     $response = $client->request(
    //         'PUT',
    //         'https://forms.kizeo.com/rest/v3/lists/' . $agencyListId, [
    //             'headers' => [
    //                 'Accept' => 'application/json',
    //                 'Authorization' => $_ENV["KIZEO_API_TOKEN"],
    //             ],
    //             'json'=>[
    //                 'items' => $agencyEquipments,
    //             ]
    //         ]
    //     );
    // }

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
     * --------------------------------------------------- EXPORT PDF AND SAVE IN ASSETS/PDF FOLDER ------------------------------
     * --------------------------------------------------------------------------------------------------------------------------
     */
    public function savePdfInAssetsPdfFolder($cache){

        // -----------------------------   Return all forms in an array | cached for 2419200 seconds 1 month
        $allFormsArray = $cache->get('all-forms-on-kizeo', function(ItemInterface $item){
            $item->expiresAfter(2419200);
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
        // ----------------------------------------------------------- Appel de 10 formulaires à la fois en mettant le paramètre LIMIT à 10 en fin d'url
        // --------------- Remise à zéro du tableau $formMaintenanceUnread  ------------------
        // --------------- Avant de le recharger avec les prochains 10 formulaires non lus  ------------------
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
                    // SDDCC bon en BDD mais pas en enregistrement des pdf
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
                                dump('Nom en erreur:   ' . $OneFormMaintenanceUnread['nom_client']['value']);
                                break;
                        }
            
                        // // -------------------------------------------            MARK FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        // $response = $this->client->request(
                        //     'POST',
                        //     'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
                        //         'headers' => [
                        //             'Accept' => 'application/json',
                        //             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        //         ],
                        //         'json' => [
                        //             "data_ids" => [intval($form['_id'])]
                        //         ]
                        //     ]
                        // );
                        // $dataOfResponse = $response->getContent();
                        
                        
                        // // -------------------------------------------            MARKED FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
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
                                dump('Nom en erreur:   ' . $OneFormMaintenanceUnread['nom_client']['value']);
                                break;
                        }
                        
                        // // -------------------------------------------            MARK FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        // $response = $this->client->request(
                        //     'POST',
                        //     'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
                        //         'headers' => [
                        //             'Accept' => 'application/json',
                        //             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        //         ],
                        //         'json' => [
                        //             "data_ids" => [intval($form['_id'])]
                        //         ]
                        //     ]
                        // );
                        // $dataOfResponse = $response->getContent();
                        
                        
                        // // -------------------------------------------            MARKED FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        
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
                                dump('Nom en erreur:   ' . $OneFormMaintenanceUnread['nom_client']['value']);
                                break;
                        }
                        
                        // // -------------------------------------------            MARK FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        // $response = $this->client->request(
                        //     'POST',
                        //     'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
                        //         'headers' => [
                        //             'Accept' => 'application/json',
                        //             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        //         ],
                        //         'json' => [
                        //             "data_ids" => [intval($form['_id'])]
                        //         ]
                        //     ]
                        // );
                        // $dataOfResponse = $response->getContent();
                        
                        
                        // // -------------------------------------------            MARKED FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        
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
                                dump('Nom en erreur:   ' . $OneFormMaintenanceUnread['nom_client']['value']);
                                break;
                        }
                        
                        // // -------------------------------------------            MARK FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        // $response = $this->client->request(
                        //     'POST',
                        //     'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
                        //         'headers' => [
                        //             'Accept' => 'application/json',
                        //             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        //         ],
                        //         'json' => [
                        //             "data_ids" => [intval($form['_id'])]
                        //         ]
                        //     ]
                        // );
                        // $dataOfResponse = $response->getContent();
                        
                        
                        // // -------------------------------------------            MARKED FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        
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
                                dump('Nom en erreur:   ' . $OneFormMaintenanceUnread['nom_client']['value']);
                                break;
                        }
                        
                        // // -------------------------------------------            MARK FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        // $response = $this->client->request(
                        //     'POST',
                        //     'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
                        //         'headers' => [
                        //             'Accept' => 'application/json',
                        //             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        //         ],
                        //         'json' => [
                        //             "data_ids" => [intval($form['_id'])]
                        //         ]
                        //     ]
                        // );
                        // $dataOfResponse = $response->getContent();
                        
                        
                        // // -------------------------------------------            MARKED FORM AS READ !!!
                        // // ------------------------------------------------------------------------------
                        
                    }else{
                        dump("Ce formulaire n\'est pas un formulaire de maintenance" );
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

        // foreach ($formUnreadArray as $form) {
            // ------------------------------------------      GET to receive PDF FROM FORMS FROM TECHNICIANS WHITH PICTURES
            // $responseData = $this->client->request(
            //     'GET',
            //     'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/data/' . $form['_id'] . '/pdf', [
            //         'headers' => [
            //             'Accept' => 'application/pdf',
            //             'Authorization' => $_ENV["KIZEO_API_TOKEN"],
            //         ],
            //     ]
            // );
            // $content = $responseData->getContent();
    
            // # Création des fichiers
                
            // $pathStructureToFile = 'assets/pdf/maintenance/' . $form['code_agence']  . '/' . $form['nom_client'] . '/' . substr($form['date_et_heure1'], 0, 4);
            // $normalNameOfTheFile = $form['nom_client'] . '-' . $form['code_agence'] . '.pdf';
            
            // // Ajouté pour voir si cela fix le mauvais nommage des dossiers PDF sur le nom de la visite de maintenance. Ex: SDCC est enregistré en CE2 au lieu de CEA
            // // SDDCC bon en BDD mais pas en enregistrement des pdf
            // // for ($i=0; $i < count($dataOfFormMaintenanceUnread); $i++) { 
            //     # code...
            //     if (str_contains($dataOfFormMaintenanceUnread[0]['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE1')) {
            //         # ------------------------------------------------------------------ ---------------------------    code CE1
            //         switch (str_contains($form['nom_client'], '/')) {
            //             case false:
            //                 if (!file_exists($pathStructureToFile . '/CE1' . '/' . "CE1-" . $normalNameOfTheFile)){
            //                     mkdir($pathStructureToFile . '/CE1', 0777, true);
            //                     file_put_contents( $pathStructureToFile . '/CE1' . '/' . "CE1-" . $normalNameOfTheFile , $content, LOCK_EX);
            //                     break;
            //                 }
                    
            //             case true:
            //                 $nomClient = $form['nom_client'];
            //                 $nomClientClean = str_replace("/", "", $nomClient);
            //                 $cleanPathStructureToTheFile = 'assets/pdf/maintenance/' . $form['code_agence']  . '/' . $nomClientClean . '/' . substr($form['date_et_heure1'], 0, 4) . '/CE1';
            //                 $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE1-" . $nomClientClean . '-' . $form['code_agence'] . '.pdf';
        
            //                 if (!file_exists($cleanNameOfTheFile)){
            //                     mkdir($cleanPathStructureToTheFile, 0777, true);
            //                     file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
            //                 }
            //                 break;
        
            //             default:
            //                 dump('Nom en erreur:   ' . $form['nom_client']);
            //                 break;
            //         }
        
            //         // -------------------------------------------            MARK FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
            //         $response = $this->client->request(
            //             'POST',
            //             'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
            //                 'headers' => [
            //                     'Accept' => 'application/json',
            //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
            //                 ],
            //                 'json' => [
            //                     "data_ids" => [intval($form['_id'])]
            //                 ]
            //             ]
            //         );
            //         $dataOfResponse = $response->getContent();
                    
                    
            //         // -------------------------------------------            MARKED FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
            //     }
            //     elseif (str_contains($dataOfFormMaintenanceUnread[0]['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE2')) {
            //         # ------------------------------------------------------------------ ---------------------------    code CE2
            //         switch (str_contains($form['nom_client'], '/')) {
            //             case false:
            //                 if (!file_exists($pathStructureToFile . '/CE2' . '/' . "CE2-" . $normalNameOfTheFile)){
            //                     mkdir($pathStructureToFile . '/CE2', 0777, true);
            //                     file_put_contents( $pathStructureToFile . '/CE2' . '/' . "CE2-" . $normalNameOfTheFile , $content, LOCK_EX);
            //                     break;
            //                 }
                    
            //             case true:
            //                 $nomClient = $form['nom_client'];
            //                 $nomClientClean = str_replace("/", "", $nomClient);
            //                 $cleanPathStructureToTheFile = 'assets/pdf/maintenance/' . $form['code_agence']  . '/' . $nomClientClean . '/' . substr($form['date_et_heure1'], 0, 4)  . '/CE2';
            //                 $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE2-" . $nomClientClean . '-' . $form['code_agence'] . '.pdf';
        
            //                 if (!file_exists($cleanNameOfTheFile)){
            //                     mkdir($cleanPathStructureToTheFile, 0777, true);
            //                     file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
            //                 }
            //                 break;
        
            //             default:
            //                 dump('Nom en erreur:   ' . $form['nom_client']);
            //                 break;
            //         }
                    
            //         // -------------------------------------------            MARK FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
            //         $response = $this->client->request(
            //             'POST',
            //             'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
            //                 'headers' => [
            //                     'Accept' => 'application/json',
            //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
            //                 ],
            //                 'json' => [
            //                     "data_ids" => [intval($form['_id'])]
            //                 ]
            //             ]
            //         );
            //         $dataOfResponse = $response->getContent();
                    
                    
            //         // -------------------------------------------            MARKED FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
                    
            //     }
            //     elseif (str_contains($dataOfFormMaintenanceUnread[0]['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE3')) {
            //         # ------------------------------------------------------------------ ---------------------------    code CE3
            //         switch (str_contains($form['nom_client'], '/')) {
            //             case false:
            //                 if (!file_exists($pathStructureToFile . '/CE3' . '/' . "CE3-" . $normalNameOfTheFile)){
            //                     mkdir($pathStructureToFile . '/CE3', 0777, true);
            //                     file_put_contents( $pathStructureToFile . '/CE3' . '/' . "CE3-" . $normalNameOfTheFile , $content, LOCK_EX);
            //                     break;
            //                 }
                    
            //             case true:
            //                 $nomClient = $form['nom_client'];
            //                 $nomClientClean = str_replace("/", "", $nomClient);
            //                 $cleanPathStructureToTheFile = 'assets/pdf/maintenance/' . $form['code_agence']  . '/' . $nomClientClean . '/' . substr($form['date_et_heure1'], 0, 4)  . '/CE3';
            //                 $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE3-" . $nomClientClean . '-' . $form['code_agence'] . '.pdf';
        
            //                 if (!file_exists($cleanNameOfTheFile)){
            //                     mkdir($cleanPathStructureToTheFile, 0777, true);
            //                     file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
            //                 }
            //                 break;
        
            //             default:
            //                 dump('Nom en erreur:   ' . $form['nom_client']);
            //                 break;
            //         }
                    
            //         // -------------------------------------------            MARK FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
            //         $response = $this->client->request(
            //             'POST',
            //             'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
            //                 'headers' => [
            //                     'Accept' => 'application/json',
            //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
            //                 ],
            //                 'json' => [
            //                     "data_ids" => [intval($form['_id'])]
            //                 ]
            //             ]
            //         );
            //         $dataOfResponse = $response->getContent();
                    
                    
            //         // -------------------------------------------            MARKED FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
                    
            //     }
            //     elseif (str_contains($dataOfFormMaintenanceUnread[0]['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CE4')) {
            //         # ------------------------------------------------------------------ ---------------------------    code CE4
            //         switch (str_contains($form['nom_client'], '/')) {
            //             case false:
            //                 if (!file_exists($pathStructureToFile . '/CE4' . '/' . "CE4-" . $normalNameOfTheFile)){
            //                     mkdir($pathStructureToFile . '/CE4', 0777, true);
            //                     file_put_contents( $pathStructureToFile . '/CE4' . '/' . "CE4-" . $normalNameOfTheFile , $content, LOCK_EX);
            //                     break;
            //                 }
                    
            //             case true:
            //                 $nomClient = $form['nom_client'];
            //                 $nomClientClean = str_replace("/", "", $nomClient);
            //                 $cleanPathStructureToTheFile = 'assets/pdf/maintenance/' . $form['code_agence']  . '/' . $nomClientClean . '/' . substr($form['date_et_heure1'], 0, 4)  . '/CE4';
            //                 $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CE4-" . $nomClientClean . '-' . $form['code_agence'] . '.pdf';
        
            //                 if (!file_exists($cleanNameOfTheFile)){
            //                     mkdir($cleanPathStructureToTheFile, 0777, true);
            //                     file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
            //                 }
            //                 break;
        
            //             default:
            //                 dump('Nom en erreur:   ' . $form['nom_client']);
            //                 break;
            //         }
                    
            //         // -------------------------------------------            MARK FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
            //         $response = $this->client->request(
            //             'POST',
            //             'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
            //                 'headers' => [
            //                     'Accept' => 'application/json',
            //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
            //                 ],
            //                 'json' => [
            //                     "data_ids" => [intval($form['_id'])]
            //                 ]
            //             ]
            //         );
            //         $dataOfResponse = $response->getContent();
                    
                    
            //         // -------------------------------------------            MARKED FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
                    
            //     }
            //     elseif (str_contains($dataOfFormMaintenanceUnread[0]['contrat_de_maintenance']['value'][0]['equipement']['path'], 'CEA')) {
            //         # ------------------------------------------------------------------ ---------------------------    code CEA
            //         switch (str_contains($form['nom_client'], '/')) {
            //             case false:
            //                 if (!file_exists($pathStructureToFile . '/CEA' . '/' . "CEA-" . $normalNameOfTheFile)){
            //                     mkdir($pathStructureToFile . '/CEA', 0777, true);
            //                     file_put_contents( $pathStructureToFile . '/CEA' . '/' . "CEA-" . $normalNameOfTheFile , $content, LOCK_EX);
            //                     break;
            //                 }
                    
            //             case true:
            //                 $nomClient = $form['nom_client'];
            //                 $nomClientClean = str_replace("/", "", $nomClient);
            //                 $cleanPathStructureToTheFile = 'assets/pdf/maintenance/' . $form['code_agence']  . '/' . $nomClientClean . '/' . substr($form['date_et_heure1'], 0, 4)  . '/CEA';
            //                 $cleanNameOfTheFile = $cleanPathStructureToTheFile . '/' . "CEA-" . $nomClientClean . '-' . $form['code_agence'] . '.pdf';
        
            //                 if (!file_exists($cleanNameOfTheFile)){
            //                     mkdir($cleanPathStructureToTheFile, 0777, true);
            //                     file_put_contents($cleanNameOfTheFile , $content, LOCK_EX);
            //                 }
            //                 break;
        
            //             default:
            //                 dump('Nom en erreur:   ' . $form['nom_client']);
            //                 break;
            //         }
                    
            //         // -------------------------------------------            MARK FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
            //         $response = $this->client->request(
            //             'POST',
            //             'https://forms.kizeo.com/rest/v3/forms/' .  $form['_form_id'] . '/markasreadbyaction/read', [
            //                 'headers' => [
            //                     'Accept' => 'application/json',
            //                     'Authorization' => $_ENV["KIZEO_API_TOKEN"],
            //                 ],
            //                 'json' => [
            //                     "data_ids" => [intval($form['_id'])]
            //                 ]
            //             ]
            //         );
            //         $dataOfResponse = $response->getContent();
                    
                    
            //         // -------------------------------------------            MARKED FORM AS READ !!!
            //         // ------------------------------------------------------------------------------
                    
            //     }else{
            //         dump("Ce formulaire n\'est pas un formulaire de maintenance" );
            //     }
            // }
        // }
        dump("Le PDF a bien été sauvegardé");
    }


    /**      --------------------------------------------------------------------------------------------------------------------
    *      ---------------------------------------------  SAVE PDF STANDARD FROM KIZEO AND SAVE EQUIPMENTS IN BDD ---------------
    *      ----------------------------------------------------------------------------------------------------------------------
    *
    * Function to save PDF with pictures for maintenance equipements in directories on O2switch  -------------- LOCAL FUNCTIONNAL -------
    * Implementation du cache symfony pour améliorer la performance en remote
    */
    public function saveEquipmentsInDatabase($cache){
        // -----------------------------   Return all forms in an array | cached for 3600 seconds 1 hour
        $allFormsArray = $cache->get('all-forms-on-kizeo', function(ItemInterface $item){
            $item->expiresAfter(3600);
            $result = FormRepository::getForms();
            return $result['forms'];
        });

        // $allFormsMaintenanceArray = []; // All forms with class "MAINTENANCE
        // $unreadFormCounter = 0;
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
        
        // ----------------------------- GET ALL ID from forms with class "MAINTENANCE" WITH CACHE  1 week
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
                // array_push($dataOfFormMaintenanceUnread, $result['data']['fields']);
                array_push($dataOfFormMaintenanceUnread, $result);
            }
        }
        
        // ------------- Upload pictures equipements en BDD local
        foreach ($dataOfFormMaintenanceUnread as $equipements){
            $equipements = $equipements['data'];
            FormRepository::uploadPicturesInDatabase($equipements);
        }

        // ------------- Selon le code agence, enregistrement des equipements en BDD local
        foreach ($dataOfFormMaintenanceUnread as $equipements){
            $equipements = $equipements['data']['fields'];
            // dd($equipements);
        // foreach ($equipements as $equipement) {
            // ----------------------------------------------------------   
            // IF code_agence d'$equipement = S50 ou S100 ou etc... on boucle sur ses équipements supplémentaires
            // ----------------------------------------------------------
            switch ($equipements['code_agence']['value']) {
                // Passer à la fonction createAndSaveInDatabaseByAgency()
                // les variables $equipements avec les nouveaux équipements des formulaires de maintenance, le tableau des résumés de l'agence et son entité ex: $entiteEquipementS10
                case 'S10':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS10);
                    break;
                
                case 'S40':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS40);
                    break;
                
                case 'S50':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements,  $entiteEquipementS50);
                    break;
                
                
                case 'S60':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS60);
                    break;
                
                
                case 'S70':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS70);
                    break;
                
                
                case 'S80':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS80);
                    break;
                
                
                case 'S100':
                    FormRepository::createAndSaveInDatabaseByAgency($equipements,  $entiteEquipementS100);
                    break;
                
                
                case 'S120':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS120);
                    break;
                
                
                case 'S130':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS130);
                    break;
                
                
                case 'S140':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS140);
                    break;
                
                
                case 'S150':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS150);
                    break;
                
                
                case 'S160':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS160);
                    break;
                
                
                case 'S170':
                    // FormRepository::createAndSaveInDatabaseByAgency($equipements, $entiteEquipementS170);
                    break;
                
                default:
                    dump('Le code agence n\'est pas prévu dans le code');
                    break;
            }
            
        // }
    }
        
        return "L'enregistrement en base de données s'est bien déroulé";
    }


    /**
     * Function to mark maintenance forms as UNREAD 
     */
    public function markMaintenanceFormsAsUnread(){
        // Récupérer les fichiers PDF dans un tableau
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

        foreach ($allFormsMaintenanceArray as $formMaintenance) {
            // -------------------------------------------            MARK FORM AS UNREAD !!!
            // ------------------------------------------------------------------------------
            $response = $this->client->request(
                'POST',
                'https://forms.kizeo.com/rest/v3/forms/' .  $formMaintenance['_form_id'] . '/markasunreadbyaction/read', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'json' => [
                        "data_ids" => [intval($formMaintenance['_id'])]
                    ]
                ]
            );
            $dataOfResponse = $response->getContent();
            
            // -------------------------------------------            MARKED FORM AS UNREAD !!!
            // ------------------------------------------------------------------------------
            
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
                        dump('Nom en erreur:   ' . $allFormsPortailsArray[$key]['liste_clients']);
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
        // On sauvegarde les équipements issus des formulaires non lus en BDD
        foreach ($equipements['fields']['contrat_de_maintenance']['value']  as $additionalEquipment){
            $equipement = new Form;

            $equipement->setFormId($equipements['form_id']);
            $equipement->setDataId($equipements['id']);
            $equipement->setUpdateTime($equipements['update_time']);
            
            $equipement->setCodeEquipement($additionalEquipment['equipement']['value']);
            $equipement->setRaisonSocialeVisite($additionalEquipment['equipement']['path']);
            $equipement->setPhotoPlaque($additionalEquipment['photo_plaque']['value']);
            $equipement->setPhotoChoc($additionalEquipment['photo_choc']['value']);
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
            
            dump("Les photos de " . $additionalEquipment['equipement']['value'] . " ont été sauvegardés en BDD");
            
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $this->getEntityManager()->persist($equipement);
            // actually executes the queries (i.e. the INSERT query)
            $this->getEntityManager()->flush();
            
            // }
        }
    }

    public function getJpgPictureFromStringName($value, $entityManager){
        dump($value);
        $picturesNames = [$value->photo_plaque, $value->photo_choc, $value->photo_choc_montant, $value->photo_panneau_intermediaire_i, $value->photo_panneau_bas_inter_ext, $value->photo_lame_basse__int_ext, $value->photo_lame_intermediaire_int_, $value->photo_envirronement_eclairage, $value->photo_bache, $value->photo_marquage_au_sol, $value->photo_environnement_equipement1, $value->photo_coffret_de_commande, $value->photo_carte, $value->photo_rail, $value->photo_equerre_rail, $value->photo_fixation_coulisse, $value->photo_moteur, $value->photo_deformation_plateau, $value->photo_deformation_plaque, $value->photo_deformation_structure, $value->photo_deformation_chassis, $value->photo_deformation_levre, $value->photo_fissure_cordon, $value->photo_joue, $value->photo_butoir, $value->photo_vantail, $value->photo_linteau, $value->photo_barriere, $value->photo_tourniquet, $value->photo_sas, $value->photo_marquage_au_sol_, $value->photo_marquage_au_sol_2, $value->photo_2];
        
        $the_picture = [];
        
        foreach ($picturesNames as $pictureName) {
            dump('Je suis picture name ligne 1870 : ' . $pictureName);
            if (!str_contains($pictureName, ", ")) {
                if ($pictureName != "" || $pictureName != null) {
                    $response = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' .  $value->form_id . '/data/' . $value->data_id . '/medias/' . $pictureName, [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );
                    $photoJpg = $response->getContent();
                    array_push($the_picture, $photoJpg);
                }
            }
            else{
                $photosSupplementaires = explode(", ", $pictureName);
                foreach ($photosSupplementaires as $photo) {
                    // dump('Je suis la photo supplémentaire ligne 1889 : ' . $photo);
                    // Call kizeo url to get jpeg here and encode the result
                    $response = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' .  $value->form_id . '/data/' . $value->data_id . '/medias/' . $photo, [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );
                    $photoJpg = $response->getContent();
                    array_push($the_picture, $photoJpg);
                }
            }
        }
        return $the_picture;
    }

    public function getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment){
        $picturesdata = [];
        $photoJpg ="";
        foreach ($picturesArray as $key => $value) {
            // if ($equipment->getRaisonSociale() . "\\" . $equipment->getVisite() === $value->raison_sociale_visite) {
                $photoJpg = $entityManager->getRepository(Form::class)->getJpgPictureFromStringName($value, $entityManager); // It's an array now
                foreach ($photoJpg as $photo) {
                    $pictureEncoded = base64_encode($photo);
                    array_push($picturesdata, $pictureEncoded);
                }
            // }
        }
        return $picturesdata;
    }
    
}
