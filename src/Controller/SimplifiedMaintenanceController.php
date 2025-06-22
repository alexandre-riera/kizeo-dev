<?php

namespace App\Controller;

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
use App\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SimplifiedMaintenanceController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Traiter les formulaires de maintenance par agence - VERSION CORRIGÉE
     * Cette version récupère TOUS les formulaires, pas seulement les non lus
     */
    #[Route('/api/maintenance/simple/{agencyCode}', name: 'app_maintenance_simple_agency', methods: ['GET'])]
    public function processMaintenanceSimple(
        string $agencyCode,
        FormRepository $formRepository,
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        Request $request
    ): JsonResponse {
        
        // Configuration pour éviter les timeouts et problèmes mémoire
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        gc_enable();
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        try {
            // CORRECTION : Récupérer TOUS les formulaires de maintenance (pas seulement les non lus)
            $maintenanceData = $this->getAllMaintenanceFormsData($cache);
            
            if (empty($maintenanceData)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune donnée de maintenance trouvée',
                    'agency' => $agencyCode,
                    'processed' => 0
                ]);
            }

            $processed = 0;
            $contractEquipments = 0;
            $offContractEquipments = 0;
            $errors = [];

            // Filtrer et traiter par agence
            foreach ($maintenanceData as $index => $formData) {
                try {
                    if (!isset($formData['data']['fields'])) {
                        continue;
                    }

                    $fields = $formData['data']['fields'];
                    
                    // Vérifier si c'est la bonne agence
                    if (!isset($fields['code_agence']['value']) || 
                        $fields['code_agence']['value'] !== $agencyCode) {
                        continue;
                    }

                    // Traiter ce formulaire
                    $result = $this->processAgencyForm($fields, $agencyCode, $entityManager);
                    
                    $contractEquipments += $result['contract'];
                    $offContractEquipments += $result['off_contract'];
                    $processed++;

                    // Marquer comme lu
                    $this->markFormAsRead($formData['form_id'], $formData['id']);

                    // Libérer la mémoire après chaque traitement
                    unset($maintenanceData[$index]);
                    
                    // Forcer le garbage collector toutes les 5 itérations
                    if ($processed % 5 === 0) {
                        gc_collect_cycles();
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'form_id' => $formData['form_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    error_log("Erreur traitement formulaire agence {$agencyCode}: " . $e->getMessage());
                }
            }

            // Sauvegarder en base
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed' => $processed,
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'errors' => $errors,
                'message' => "Traitement terminé pour l'agence {$agencyCode}: {$processed} formulaires traités"
            ]);

        } catch (\Exception $e) {
            error_log("Erreur generale agence {$agencyCode}: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'agency' => $agencyCode,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NOUVELLE MÉTHODE : Récupérer TOUS les formulaires de maintenance (pas seulement les non lus)
     */
    private function getAllMaintenanceFormsData($cache): array
    {
        // Cache des formulaires MAINTENANCE pour 1 heure
        $allFormsArray = $cache->get('all-forms-on-kizeo-complete', function($item){
            $item->expiresAfter(3600); // 1 heure au lieu de 1 mois
            $result = $this->getForms();
            return $result['forms'];
        });

        $formMaintenanceIds = [];
        $allMaintenanceData = [];
        
        // Récupérer tous les IDs des formulaires MAINTENANCE
        foreach ($allFormsArray as $form) {
            if ($form['class'] === 'MAINTENANCE') {
                $formMaintenanceIds[] = $form['id'];
            }
        }

        // Pour chaque formulaire MAINTENANCE, récupérer TOUTES les données (pas seulement les non lues)
        foreach ($formMaintenanceIds as $formId) {
            try {
                // Utiliser l'endpoint /data/advanced pour récupérer TOUTES les données
                $response = $this->client->request(
                    'POST',
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced', 
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'timeout' => 30
                    ]
                );

                $formData = $response->toArray();
                
                if (isset($formData['data']) && !empty($formData['data'])) {
                    foreach ($formData['data'] as $entry) {
                        // Récupérer les détails de chaque entrée
                        $detailResponse = $this->client->request(
                            'GET',
                            'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                            [
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                ],
                                'timeout' => 15
                            ]
                        );

                        $detailData = $detailResponse->toArray();
                        
                        // Ajouter les informations nécessaires pour le traitement
                        $allMaintenanceData[] = [
                            'form_id' => $entry['_form_id'],
                            'id' => $entry['_id'],
                            'data' => $detailData['data']
                        ];
                    }
                }

            } catch (\Exception $e) {
                error_log("Erreur récupération données formulaire {$formId}: " . $e->getMessage());
                continue;
            }
        }

        return $allMaintenanceData;
    }

    /**
     * Récupérer la liste des formulaires depuis Kizeo
     */
    private function getForms(): array
    {
        $response = $this->client->request(
            'GET',
            'https://forms.kizeo.com/rest/v3/forms', 
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'timeout' => 30
            ]
        );
        
        return $response->toArray();
    }

    /**
     * Traiter un formulaire spécifique pour une agence
     */
    private function processAgencyForm(array $fields, string $agencyCode, EntityManagerInterface $entityManager): array
    {
        $contractEquipments = 0;
        $offContractEquipments = 0;
        
        $entityClass = $this->getEntityClassByAgency($agencyCode);
        if (!$entityClass) {
            throw new \Exception("Classe d'entité non trouvée pour l'agence: " . $agencyCode);
        }

        // Traitement des équipements sous contrat
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            foreach ($fields['contrat_de_maintenance']['value'] as $equipmentContrat) {
                $equipement = new $entityClass();
                $this->setCommonEquipmentData($equipement, $fields);
                $this->setContractEquipmentData($equipement, $equipmentContrat);
                
                $entityManager->persist($equipement);
                $contractEquipments++;
            }
        }

        // Traitement des équipements hors contrat
        if (isset($fields['hors_contrat']['value']) && !empty($fields['hors_contrat']['value'])) {
            foreach ($fields['hors_contrat']['value'] as $equipmentHorsContrat) {
                $equipement = new $entityClass();
                $this->setCommonEquipmentData($equipement, $fields);
                $this->setOffContractEquipmentData($equipement, $equipmentHorsContrat, $fields, $entityClass, $entityManager);
                
                $entityManager->persist($equipement);
                $offContractEquipments++;
            }
        }

        return [
            'contract' => $contractEquipments,
            'off_contract' => $offContractEquipments
        ];
    }

    // ... Le reste des méthodes reste identique (setCommonEquipmentData, setContractEquipmentData, etc.) ...
    
    /**
     * Définir les données communes à tous les équipements
     */
    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdClientMaintenance($fields['id_client_']['value'] ?? '');
        $equipement->setNomClientMaintenance($fields['nom_du_client']['value'] ?? '');
        $equipement->setAdresseClientMaintenance($fields['adresse_du_client']['value'] ?? '');
        $equipement->setVilleClientMaintenance($fields['ville_du_client']['value'] ?? '');
        $equipement->setCodePostalClientMaintenance($fields['code_postal_du_client']['value'] ?? '');
        $equipement->setTechnicienMaintenance($fields['technicien']['value'] ?? '');
        $equipement->setDateInterventionMaintenance(new \DateTime($fields['date_et_heure']['value'] ?? 'now'));
        $equipement->setDateSauvegarde(new \DateTime());
        $equipement->setDateModification(new \DateTime());
    }

    /**
     * Définir les données spécifiques aux équipements sous contrat
     */
    private function setContractEquipmentData($equipement, array $equipmentContrat): void
    {
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $equipementValue = $equipmentContrat['equipement']['value'] ?? '';
        
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        $equipmentInfo = $this->parseEquipmentInfo($equipementValue);
        
        $equipement->setNumeroEquipement($equipmentInfo['numero'] ?? '');
        $equipement->setLibelleEquipement($equipmentInfo['libelle'] ?? '');
        $equipement->setMiseEnService($equipmentInfo['mise_en_service'] ?? '');
        $equipement->setNumeroDeSerie($equipmentInfo['numero_serie'] ?? '');
        $equipement->setMarque($equipmentInfo['marque'] ?? '');
        $equipement->setHauteur($equipmentInfo['hauteur'] ?? '');
        $equipement->setLargeur($equipmentInfo['largeur'] ?? '');
        $equipement->setRepereSiteClient($equipmentInfo['repere'] ?? '');
        
        $equipement->setModeFonctionnement($equipmentContrat['mode_fonctionnement']['value'] ?? '');
        $equipement->setLongueur($equipmentContrat['longueur']['value'] ?? 'NC');
        $equipement->setPlaqueSignaletique($equipmentContrat['plaque_signaletique']['value'] ?? '');
        $equipement->setEtat($equipmentContrat['etat']['value'] ?? '');
        
        $equipement->setStatutDeMaintenance($this->getMaintenanceStatusFromEtat($equipmentContrat['etat']['value'] ?? ''));
        
        $equipement->setEnMaintenance(true);
        $equipement->setIsArchive(false);
    }

    /**
     * Définir les données spécifiques aux équipements hors contrat avec numérotation automatique
     */
    private function setOffContractEquipmentData($equipement, array $equipmentHorsContrat, array $fields, string $entityClass, EntityManagerInterface $entityManager): void
    {
        $typeLibelle = strtolower($equipmentHorsContrat['nature']['value'] ?? '');
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        $idClient = $fields['id_client_']['value'] ?? '';
        
        $nouveauNumero = $this->getNextEquipmentNumber($typeCode, $idClient, $entityClass, $entityManager);
        $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
        
        $equipement->setNumeroEquipement($numeroFormate);
        $equipement->setLibelleEquipement($typeLibelle);
        $equipement->setModeFonctionnement($equipmentHorsContrat['mode_fonctionnement_']['value'] ?? '');
        $equipement->setRepereSiteClient($equipmentHorsContrat['localisation_site_client1']['value'] ?? '');
        $equipement->setMiseEnService($equipmentHorsContrat['annee']['value'] ?? '');
        $equipement->setNumeroDeSerie($equipmentHorsContrat['n_de_serie']['value'] ?? '');
        $equipement->setMarque($equipmentHorsContrat['marque']['value'] ?? '');
        $equipement->setLargeur($equipmentHorsContrat['largeur']['value'] ?? '');
        $equipement->setHauteur($equipmentHorsContrat['hauteur']['value'] ?? '');
        $equipement->setPlaqueSignaletique($equipmentHorsContrat['plaque_signaletique1']['value'] ?? '');
        $equipement->setEtat($equipmentHorsContrat['etat1']['value'] ?? '');
        
        $equipement->setVisite($this->getDefaultVisitType($fields));
        $equipement->setStatutDeMaintenance($this->getMaintenanceStatusFromEtat($equipmentHorsContrat['etat1']['value'] ?? ''));
        
        $equipement->setEnMaintenance(false);
        $equipement->setIsArchive(false);
    }

    /**
     * Marquer un formulaire comme lu
     */
    private function markFormAsRead(string $formId, string $dataId): void
    {
        try {
            $this->client->request('POST', 
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/markasreadbyaction/enfintraite", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                ],
                'json' => [
                    "data_ids" => [intval($dataId)]
                ],
                'timeout' => 10
            ]);
        } catch (\Exception $e) {
            error_log("Erreur markFormAsRead: " . $e->getMessage());
        }
    }

    // ... Les autres méthodes utilitaires restent identiques ...
    
    private function getEntityClassByAgency(string $codeAgence): ?string
    {
        $agencyMap = [
            'S10' => EquipementS10::class, 'S40' => EquipementS40::class, 'S50' => EquipementS50::class,
            'S60' => EquipementS60::class, 'S70' => EquipementS70::class, 'S80' => EquipementS80::class,
            'S100' => EquipementS100::class, 'S120' => EquipementS120::class, 'S130' => EquipementS130::class,
            'S140' => EquipementS140::class, 'S150' => EquipementS150::class, 'S160' => EquipementS160::class,
            'S170' => EquipementS170::class,
        ];
        return $agencyMap[$codeAgence] ?? null;
    }

    private function extractVisitTypeFromPath(string $path): string
    {
        if (str_contains($path, 'CE1')) return 'CE1';
        if (str_contains($path, 'CE2')) return 'CE2';
        if (str_contains($path, 'CE3')) return 'CE3';
        if (str_contains($path, 'CE4')) return 'CE4';
        if (str_contains($path, 'CEA')) return 'CEA';
        return 'CE1';
    }

    private function parseEquipmentInfo(string $equipmentValue): array
    {
        $parts = explode('|', $equipmentValue);
        
        return [
            'numero' => $parts[0] ?? '',
            'libelle' => $parts[1] ?? '',
            'mise_en_service' => $parts[2] ?? '',
            'numero_serie' => $parts[3] ?? '',
            'marque' => $parts[4] ?? '',
            'hauteur' => $parts[5] ?? '',
            'largeur' => $parts[6] ?? '',
            'repere' => $parts[7] ?? ''
        ];
    }

    private function getMaintenanceStatusFromEtat(string $etat): string
    {
        switch ($etat) {
            case "Rien à signaler le jour de la visite.":
                return "RAS";
            case "Nettoyage et Graissage":
                return "ENTRETENU";
            case "Réparation de dépannage":
                return "DEPANNE";
            case "Réparation de remise en état":
                return "REPARE";
            case "A réparer":
                return "A_REPARER";
            case "A remplacer":
                return "A_REMPLACER";
            case "Hors service":
                return "HS";
            default:
                return "RAS";
        }
    }

    private function getTypeCodeFromLibelle(string $libelle): string
    {
        $libelleUpper = strtoupper($libelle);
        
        if (str_contains($libelleUpper, 'SECTIONNELLE')) return 'SEC';
        if (str_contains($libelleUpper, 'RIDEAU')) return 'RID';
        if (str_contains($libelleUpper, 'BASCULANTE')) return 'BAS';
        if (str_contains($libelleUpper, 'COULISSANTE')) return 'COU';
        if (str_contains($libelleUpper, 'BATTANTE')) return 'BAT';
        if (str_contains($libelleUpper, 'BARRIERE')) return 'BAR';
        if (str_contains($libelleUpper, 'AUTOMATISME')) return 'AUT';
        
        return 'EQU';
    }

    private function getNextEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $repository = $entityManager->getRepository($entityClass);
        
        $equipments = $repository->createQueryBuilder('e')
            ->where('e.idClientMaintenance = :idClient')
            ->andWhere('e.numeroEquipement LIKE :typeCode')
            ->setParameter('idClient', $idClient)
            ->setParameter('typeCode', $typeCode . '%')
            ->getQuery()
            ->getResult();
        
        $lastNumber = 0;
        
        foreach ($equipments as $equipment) {
            $numeroEquipement = $equipment->getNumeroEquipement();
            
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $number = (int)$matches[1];
                if ($number > $lastNumber) {
                    $lastNumber = $number;
                }
            }
        }
        
        return $lastNumber + 1;
    }

    private function getDefaultVisitType(array $fields): string
    {
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            $firstEquipment = $fields['contrat_de_maintenance']['value'][0];
            return $this->extractVisitTypeFromPath($firstEquipment['equipement']['path'] ?? '');
        }
        
        return 'CE1';
    }

    /**
     * SOLUTION 3: Route pour marquer tous les formulaires S140 comme "non lus"
     * Pour forcer leur retraitement
     */
    #[Route('/api/maintenance/markasunread/{agencyCode}', name: 'app_maintenance_markasunread', methods: ['GET','POST'])]
    public function markAsUnreadForAgency(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        try {
            $markedCount = 0;
            $errors = [];

            // 1. Récupérer tous les formulaires MAINTENANCE
            $formsResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $allForms = $formsResponse->toArray();
            $maintenanceForms = array_filter($allForms['forms'], function($form) {
                return $form['class'] === 'MAINTENANCE';
            });

            // 2. Pour chaque formulaire, trouver les entrées S140 et les marquer comme non lues
            foreach ($maintenanceForms as $form) {
                try {
                    // Récupérer toutes les données du formulaire
                    $dataResponse = $this->client->request(
                        'POST',
                        'https://forms.kizeo.com/rest/v3/forms/' . $form['id'] . '/data/advanced',
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );

                    $formData = $dataResponse->toArray();
                    $s140DataIds = [];

                    // Identifier les entrées S140
                    foreach ($formData['data'] ?? [] as $entry) {
                        $detailResponse = $this->client->request(
                            'GET',
                            'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                            [
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                ],
                            ]
                        );

                        $detailData = $detailResponse->toArray();
                        
                        if (isset($detailData['data']['fields']['code_agence']['value']) && 
                            $detailData['data']['fields']['code_agence']['value'] === 'S140') {
                            $s140DataIds[] = intval($entry['_id']);
                        }
                    }

                    // Marquer comme non lus
                    if (!empty($s140DataIds)) {
                        $this->client->request(
                            'POST',
                            'https://forms.kizeo.com/rest/v3/forms/' . $form['id'] . '/markasunreadbyaction/read',
                            [
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                ],
                                'json' => [
                                    'data_ids' => $s140DataIds
                                ]
                            ]
                        );
                        $markedCount += count($s140DataIds);
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'form_id' => $form['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'marked_as_unread' => $markedCount,
                'errors' => $errors,
                'message' => "Marqué {$markedCount} entrées S140 comme non lues"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SOLUTION 4: Route simplifiée qui force le traitement sans vérifier le statut "lu/non lu"
     */
    #[Route('/api/maintenance/force/{agencyCode}', name: 'app_maintenance_force_process', methods: ['GET'])]
    public function forceProcessMaintenanceByAgency(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        ini_set('memory_limit', '2G');
        set_time_limit(0);
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        try {
            $processed = 0;
            $errors = [];
            $contractEquipments = 0;
            $offContractEquipments = 0;

            // 1. Récupérer directement TOUS les formulaires MAINTENANCE
            $formsResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $allForms = $formsResponse->toArray();
            $maintenanceForms = array_filter($allForms['forms'], function($form) {
                return $form['class'] === 'MAINTENANCE';
            });

            // 2. Traiter chaque formulaire MAINTENANCE
            foreach ($maintenanceForms as $form) {
                try {
                    // Récupérer toutes les données (ignore le statut lu/non lu)
                    $dataResponse = $this->client->request(
                        'POST',
                        'https://forms.kizeo.com/rest/v3/forms/' . $form['id'] . '/data/advanced',
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );

                    $formData = $dataResponse->toArray();

                    // 3. Traiter chaque entrée du formulaire
                    foreach ($formData['data'] ?? [] as $entry) {
                        try {
                            $detailResponse = $this->client->request(
                                'GET',
                                'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                                [
                                    'headers' => [
                                        'Accept' => 'application/json',
                                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                    ],
                                ]
                            );

                            $detailData = $detailResponse->toArray();
                            $fields = $detailData['data']['fields'];

                            // 4. Vérifier si c'est la bonne agence
                            if (!isset($fields['code_agence']['value']) || 
                                $fields['code_agence']['value'] !== $agencyCode) {
                                continue;
                            }

                            // 5. Traiter cette entrée
                            $entityClass = $this->getEntityClassByAgency($agencyCode);
                            if (!$entityClass) {
                                throw new \Exception("Classe d'entité non trouvée pour: " . $agencyCode);
                            }

                            // Traitement des équipements sous contrat
                            if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
                                foreach ($fields['contrat_de_maintenance']['value'] as $equipmentContrat) {
                                    $equipement = new $entityClass();
                                    $this->setCommonEquipmentData($equipement, $fields);
                                    $this->setContractEquipmentData($equipement, $equipmentContrat);
                                    
                                    $entityManager->persist($equipement);
                                    $contractEquipments++;
                                }
                            }

                            // Traitement des équipements hors contrat
                            if (isset($fields['hors_contrat']['value']) && !empty($fields['hors_contrat']['value'])) {
                                foreach ($fields['hors_contrat']['value'] as $equipmentHorsContrat) {
                                    $equipement = new $entityClass();
                                    $this->setCommonEquipmentData($equipement, $fields);
                                    $this->setOffContractEquipmentData($equipement, $equipmentHorsContrat, $fields, $entityClass, $entityManager);
                                    
                                    $entityManager->persist($equipement);
                                    $offContractEquipments++;
                                }
                            }

                            $processed++;

                            // Sauvegarder périodiquement
                            if ($processed % 10 === 0) {
                                $entityManager->flush();
                                $entityManager->clear();
                                gc_collect_cycles();
                            }

                        } catch (\Exception $e) {
                            $errors[] = [
                                'entry_id' => $entry['_id'],
                                'error' => $e->getMessage()
                            ];
                        }
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'form_id' => $form['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Sauvegarder final
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed' => $processed,
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'total_equipments' => $contractEquipments + $offContractEquipments,
                'errors' => $errors,
                'message' => "Traitement forcé terminé pour {$agencyCode}: {$processed} formulaires, " . 
                            ($contractEquipments + $offContractEquipments) . " équipements traités"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'agency' => $agencyCode,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SOLUTION 5: Vider le cache pour S140
     */
    #[Route('/api/maintenance/clearcache/{agencyCode}', name: 'app_maintenance_clear_cache', methods: ['DELETE'])]
    public function clearCacheForAgency(
        string $agencyCode,
        CacheInterface $cache
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        try {
            // Vider tous les caches liés aux formulaires
            $cacheKeys = [
                'all-forms-on-kizeo',
                'all-forms-on-kizeo-complete',
                'maintenance_forms_list',
                'maintenance_forms_list_optimized'
            ];

            $clearedKeys = [];
            foreach ($cacheKeys as $key) {
                if ($cache->delete($key)) {
                    $clearedKeys[] = $key;
                }
            }

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'cleared_cache_keys' => $clearedKeys,
                'message' => 'Cache vidé, vous pouvez maintenant retenter le traitement'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SOLUTION OPTIMISÉE: Route qui traite formulaire par formulaire pour éviter les problèmes de mémoire
     */
    #[Route('/api/maintenance/force-lite/{agencyCode}', name: 'app_maintenance_force_lite', methods: ['GET'])]
    public function forceProcessMaintenanceLite(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration mémoire conservatrice
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 120); // 2 minutes max
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        try {
            $processed = 0;
            $errors = [];
            $contractEquipments = 0;
            $offContractEquipments = 0;
            $foundForms = [];

            // 1. Récupérer SEULEMENT la liste des formulaires (pas les données)
            $formsResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );

            $allForms = $formsResponse->toArray();
            $maintenanceForms = array_filter($allForms['forms'], function($form) {
                return $form['class'] === 'MAINTENANCE';
            });

            // 2. Traiter chaque formulaire INDIVIDUELLEMENT pour économiser la mémoire
            foreach ($maintenanceForms as $formIndex => $form) {
                try {
                    error_log("Traitement formulaire {$form['id']} ({$form['name']})");
                    
                    // Récupérer UNIQUEMENT les formulaires non lus pour commencer (plus léger)
                    $unreadResponse = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' . $form['id'] . '/data/unread/read/10',
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                            'timeout' => 20
                        ]
                    );

                    $unreadData = $unreadResponse->toArray();
                    
                    if (empty($unreadData['data'])) {
                        error_log("Aucune donnée non lue pour le formulaire {$form['id']}");
                        continue;
                    }

                    // 3. Traiter chaque entrée NON LUE une par une
                    foreach ($unreadData['data'] as $entry) {
                        try {
                            // Récupérer les détails de l'entrée
                            $detailResponse = $this->client->request(
                                'GET',
                                'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                                [
                                    'headers' => [
                                        'Accept' => 'application/json',
                                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                    ],
                                    'timeout' => 15
                                ]
                            );

                            $detailData = $detailResponse->toArray();
                            $fields = $detailData['data']['fields'];

                            // 4. Vérifier si c'est la bonne agence
                            if (!isset($fields['code_agence']['value']) || 
                                $fields['code_agence']['value'] !== $agencyCode) {
                                continue;
                            }

                            error_log("Trouvé entrée {$agencyCode}: {$entry['_id']}");
                            
                            // 5. Traiter cette entrée S140
                            $entityClass = $this->getEntityClassByAgency($agencyCode);
                            if (!$entityClass) {
                                throw new \Exception("Classe d'entité non trouvée pour: " . $agencyCode);
                            }

                            $foundForms[] = [
                                'form_id' => $form['id'],
                                'form_name' => $form['name'],
                                'entry_id' => $entry['_id'],
                                'client_name' => $fields['nom_du_client']['value'] ?? 'N/A'
                            ];

                            // Traitement des équipements sous contrat
                            if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
                                foreach ($fields['contrat_de_maintenance']['value'] as $equipmentContrat) {
                                    $equipement = new $entityClass();
                                    $this->setCommonEquipmentData($equipement, $fields);
                                    $this->setContractEquipmentData($equipement, $equipmentContrat);
                                    
                                    $entityManager->persist($equipement);
                                    $contractEquipments++;
                                }
                            }

                            // Traitement des équipements hors contrat
                            if (isset($fields['hors_contrat']['value']) && !empty($fields['hors_contrat']['value'])) {
                                foreach ($fields['hors_contrat']['value'] as $equipmentHorsContrat) {
                                    $equipement = new $entityClass();
                                    $this->setCommonEquipmentData($equipement, $fields);
                                    $this->setOffContractEquipmentData($equipement, $equipmentHorsContrat, $fields, $entityClass, $entityManager);
                                    
                                    $entityManager->persist($equipement);
                                    $offContractEquipments++;
                                }
                            }

                            $processed++;

                            // Sauvegarder et nettoyer la mémoire après chaque entrée
                            $entityManager->flush();
                            $entityManager->clear();
                            
                            // Forcer le garbage collector
                            gc_collect_cycles();

                            // NE PAS marquer comme lu pour l'instant - laisser en non lu pour debug

                        } catch (\Exception $e) {
                            $errors[] = [
                                'entry_id' => $entry['_id'] ?? 'unknown',
                                'error' => $e->getMessage()
                            ];
                            error_log("Erreur traitement entrée: " . $e->getMessage());
                        }
                    }

                    // Nettoyer la mémoire après chaque formulaire
                    unset($unreadData);
                    gc_collect_cycles();

                    // Arrêter après avoir trouvé des données pour éviter la surcharge
                    if ($processed > 0) {
                        break;
                    }

                } catch (\Exception $e) {
                    $errors[] = [
                        'form_id' => $form['id'],
                        'error' => $e->getMessage()
                    ];
                    error_log("Erreur formulaire {$form['id']}: " . $e->getMessage());
                }
            }

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed' => $processed,
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'total_equipments' => $contractEquipments + $offContractEquipments,
                'found_forms' => $foundForms,
                'errors' => $errors,
                'message' => $processed > 0 ? 
                    "Traitement réussi pour {$agencyCode}: {$processed} formulaires, " . 
                    ($contractEquipments + $offContractEquipments) . " équipements traités" :
                    "Aucun formulaire non lu trouvé pour {$agencyCode}"
            ]);

        } catch (\Exception $e) {
            error_log("Erreur générale: " . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'agency' => $agencyCode,
                'error' => $e->getMessage(),
                'recommendation' => 'Essayer la route de debug pour vérifier l\'existence des données'
            ], 500);
        }
    }

    /**
     * VERSION ENCORE PLUS SIMPLE: Juste vérifier s'il y a des données S140 non lues
     */
    #[Route('/api/maintenance/check/{agencyCode}', name: 'app_maintenance_check', methods: ['GET'])]
    public function checkMaintenanceData(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        try {
            $foundData = [];
            $totalChecked = 0;

            // 1. Récupérer la liste des formulaires
            $formsResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $allForms = $formsResponse->toArray();
            $maintenanceForms = array_filter($allForms['forms'], function($form) {
                return $form['class'] === 'MAINTENANCE';
            });

            // 2. Pour chaque formulaire, vérifier s'il y a des données non lues
            foreach ($maintenanceForms as $form) {
                try {
                    $unreadResponse = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' . $form['id'] . '/data/unread/read/5',
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );

                    $unreadData = $unreadResponse->toArray();
                    $totalChecked += count($unreadData['data'] ?? []);

                    // Vérifier s'il y a du S140 dans les non lus
                    foreach ($unreadData['data'] ?? [] as $entry) {
                        $detailResponse = $this->client->request(
                            'GET',
                            'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                            [
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                ],
                            ]
                        );

                        $detailData = $detailResponse->toArray();
                        
                        if (isset($detailData['data']['fields']['code_agence']['value']) && 
                            $detailData['data']['fields']['code_agence']['value'] === 'S140') {
                            
                            $foundData[] = [
                                'form_id' => $form['id'],
                                'form_name' => $form['name'],
                                'entry_id' => $entry['_id'],
                                'client_name' => $detailData['data']['fields']['nom_du_client']['value'] ?? 'N/A',
                                'date' => $detailData['data']['fields']['date_et_heure']['value'] ?? 'N/A'
                            ];
                        }
                    }

                } catch (\Exception $e) {
                    error_log("Erreur vérification formulaire {$form['id']}: " . $e->getMessage());
                }
            }

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'total_maintenance_forms' => count($maintenanceForms),
                'total_unread_entries_checked' => $totalChecked,
                'found_s140_unread' => count($foundData),
                's140_data' => $foundData,
                'conclusion' => count($foundData) > 0 ? 
                    'Des données S140 non lues existent - utilisez la route force-lite' : 
                    'Aucune donnée S140 non lue - toutes déjà traitées ou inexistantes'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}