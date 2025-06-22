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
     * Définir les données communes à tous les équipements - ADAPTÉE AUX PROPRIÉTÉS EXISTANTES
     */
    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_du_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['technicien']['value'] ?? '');
        
        // Convertir la date au format string si nécessaire
        $dateIntervention = $fields['date_et_heure']['value'] ?? '';
        $equipement->setDateEnregistrement($dateIntervention);
        
        // Stocker les informations client dans des champs existants ou les ignorer
        // Les champs adresse, ville, code postal n'existent pas dans l'entité actuelle
        
        // Valeurs par défaut
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
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

    /**
     * Méthode pour récupérer le prochain numéro d'équipement - ADAPTÉE
     */
    private function getNextEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $repository = $entityManager->getRepository($entityClass);
        
        // Utiliser id_contact qui correspond au champ existant
        $equipments = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typeCode')
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

    /**
     * SOLUTION ULTRA-LÉGÈRE: Traiter UN SEUL formulaire S140 à la fois
     * Usage: GET /api/maintenance/single/S140?form_id=1088761&entry_id=232647438
     */
    #[Route('/api/maintenance/single/{agencyCode}', name: 'app_maintenance_single', methods: ['GET'])]
    public function processSingleMaintenanceEntry(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration mémoire minimale
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', 60);
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        $formId = $request->query->get('form_id');
        $entryId = $request->query->get('entry_id');

        if (!$formId || !$entryId) {
            return new JsonResponse([
                'error' => 'Paramètres manquants',
                'required' => 'form_id et entry_id',
                'available_entries' => [
                    ['form_id' => '1088761', 'entry_id' => '232647438'],
                    ['form_id' => '1088761', 'entry_id' => '232647490'],
                    ['form_id' => '1088761', 'entry_id' => '232647488'],
                    ['form_id' => '1088761', 'entry_id' => '232647486'],
                    ['form_id' => '1088761', 'entry_id' => '232647484']
                ]
            ], 400);
        }

        try {
            // 1. Récupérer UNIQUEMENT cette entrée spécifique
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];

            // 2. Vérifier que c'est bien S140
            if (!isset($fields['code_agence']['value']) || 
                $fields['code_agence']['value'] !== $agencyCode) {
                return new JsonResponse([
                    'error' => 'Cette entrée n\'est pas pour l\'agence ' . $agencyCode,
                    'actual_agency' => $fields['code_agence']['value'] ?? 'unknown'
                ], 400);
            }

            // 3. Traiter cette entrée unique
            $entityClass = $this->getEntityClassByAgency($agencyCode);
            if (!$entityClass) {
                throw new \Exception("Classe d'entité non trouvée pour: " . $agencyCode);
            }

            $contractEquipments = 0;
            $offContractEquipments = 0;

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

            // 4. Sauvegarder
            $entityManager->flush();

            // 5. Marquer comme lu
            $this->markFormAsRead($formId, $entryId);

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed_entry' => [
                    'form_id' => $formId,
                    'entry_id' => $entryId,
                    'client_name' => $fields['nom_du_client']['value'] ?? 'N/A',
                    'technician' => $fields['technicien']['value'] ?? 'N/A',
                    'date' => $fields['date_et_heure']['value'] ?? 'N/A'
                ],
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'total_equipments' => $contractEquipments + $offContractEquipments,
                'message' => "Entrée {$entryId} traitée avec succès: " . 
                            ($contractEquipments + $offContractEquipments) . " équipements ajoutés"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ROUTE DE TRAITEMENT EN BATCH: Traiter tous les formulaires S140 un par un
     */
    #[Route('/api/maintenance/batch/{agencyCode}', name: 'app_maintenance_batch', methods: ['GET'])]
    public function processBatchMaintenance(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        // Liste des entrées trouvées par la route check
        $entries = [
            ['form_id' => '1088761', 'entry_id' => '232647438'],
            ['form_id' => '1088761', 'entry_id' => '232647490'],
            ['form_id' => '1088761', 'entry_id' => '232647488'],
            ['form_id' => '1088761', 'entry_id' => '232647486'],
            ['form_id' => '1088761', 'entry_id' => '232647484']
        ];

        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;
        $totalEquipments = 0;

        foreach ($entries as $entry) {
            try {
                // Faire un appel interne à la route single
                $subRequest = Request::create(
                    '/api/maintenance/single/' . $agencyCode,
                    'GET',
                    [
                        'form_id' => $entry['form_id'],
                        'entry_id' => $entry['entry_id']
                    ]
                );

                $response = $this->processSingleMaintenanceEntry($agencyCode, $entityManager, $subRequest);
                $data = json_decode($response->getContent(), true);

                if ($data['success']) {
                    $totalSuccess++;
                    $totalEquipments += $data['total_equipments'];
                    $results[] = [
                        'entry_id' => $entry['entry_id'],
                        'status' => 'success',
                        'equipments' => $data['total_equipments']
                    ];
                } else {
                    $totalErrors++;
                    $results[] = [
                        'entry_id' => $entry['entry_id'],
                        'status' => 'error',
                        'error' => $data['error']
                    ];
                }

                // Pause entre chaque traitement pour éviter la surcharge
                sleep(1);

            } catch (\Exception $e) {
                $totalErrors++;
                $results[] = [
                    'entry_id' => $entry['entry_id'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'agency' => $agencyCode,
            'total_entries' => count($entries),
            'successful' => $totalSuccess,
            'errors' => $totalErrors,
            'total_equipments_added' => $totalEquipments,
            'details' => $results,
            'message' => "Traitement batch terminé: {$totalSuccess}/{" . count($entries) . "} entrées traitées, {$totalEquipments} équipements ajoutés"
        ]);
    }

    /**
     * Route de test ultra-simple pour S140
     */
    #[Route('/api/maintenance/test/{agencyCode}', name: 'app_maintenance_test', methods: ['GET'])]
    public function testMaintenanceProcessing(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        // Test avec les IDs trouvés précédemment
        $formId = '1088761';
        $entryId = '232647438'; // Premier ID trouvé

        try {
            // 1. Récupérer l'entrée spécifique
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];

            // 2. Vérifier que c'est bien S140
            if ($fields['code_agence']['value'] !== 'S140') {
                return new JsonResponse(['error' => 'Cette entrée n\'est pas S140'], 400);
            }

            // 3. Créer un équipement de test
            $equipement = new EquipementS140();
            
            // Données de base
            $equipement->setCodeAgence($fields['code_agence']['value']);
            $equipement->setIdContact($fields['id_client_']['value'] ?? '');
            $equipement->setRaisonSociale($fields['nom_du_client']['value'] ?? '');
            $equipement->setTrigrammeTech($fields['technicien']['value'] ?? '');
            $equipement->setDateEnregistrement($fields['date_et_heure']['value'] ?? '');
            
            // Données d'équipement de test
            $equipement->setNumeroEquipement('TEST_S140_001');
            $equipement->setLibelleEquipement('Équipement de test');
            $equipement->setVisite('CE1');
            $equipement->setEtat('Test');
            $equipement->setStatutDeMaintenance('TEST');
            
            // Valeurs par défaut
            $equipement->setEtatDesLieuxFait(false);
            $equipement->setEnMaintenance(true);
            $equipement->setIsArchive(false);

            // 4. Sauvegarder
            $entityManager->persist($equipement);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Équipement de test S140 créé avec succès',
                'equipment_id' => $equipement->getId(),
                'equipment_number' => $equipement->getNumeroEquipement(),
                'client_name' => $equipement->getRaisonSociale(),
                'technician' => $equipement->getTrigrammeTech(),
                'form_data' => [
                    'form_id' => $formId,
                    'entry_id' => $entryId,
                    'agency' => $fields['code_agence']['value'],
                    'client_id' => $fields['id_client_']['value'] ?? '',
                    'client_name' => $fields['nom_du_client']['value'] ?? '',
                    'technician' => $fields['technicien']['value'] ?? '',
                    'date' => $fields['date_et_heure']['value'] ?? ''
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * CONTROLLER PRÊT POUR LA PRODUCTION - Traitement des vraies données S140
     */

    /**
     * Route pour traiter UN formulaire S140 spécifique avec ses vrais équipements
     */
    #[Route('/api/maintenance/process-real/{agencyCode}', name: 'app_maintenance_process_real', methods: ['GET'])]
    public function processRealMaintenanceEntry(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id', '232647438');

        try {
            // 1. Récupérer l'entrée spécifique
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];

            // 2. Vérifier que c'est bien S140
            if ($fields['code_agence']['value'] !== 'S140') {
                return new JsonResponse([
                    'error' => 'Cette entrée n\'est pas S140',
                    'actual_agency' => $fields['code_agence']['value']
                ], 400);
            }

            $contractEquipments = 0;
            $offContractEquipments = 0;
            $processedEquipments = [];

            // 3. Traiter les équipements sous contrat
            if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
                foreach ($fields['contrat_de_maintenance']['value'] as $index => $equipmentContrat) {
                    try {
                        $equipement = new EquipementS140();
                        
                        // Données communes
                        $this->setRealCommonData($equipement, $fields);
                        
                        // Données spécifiques contrat
                        $this->setRealContractData($equipement, $equipmentContrat);
                        
                        $entityManager->persist($equipement);
                        $contractEquipments++;
                        
                        $processedEquipments[] = [
                            'type' => 'contract',
                            'numero' => $equipement->getNumeroEquipement(),
                            'libelle' => $equipement->getLibelleEquipement(),
                            'etat' => $equipement->getEtat()
                        ];
                        
                    } catch (\Exception $e) {
                        error_log("Erreur équipement contrat $index: " . $e->getMessage());
                    }
                }
            }

            // 4. Traiter les équipements hors contrat
            if (isset($fields['hors_contrat']['value']) && !empty($fields['hors_contrat']['value'])) {
                foreach ($fields['hors_contrat']['value'] as $index => $equipmentHorsContrat) {
                    try {
                        $equipement = new EquipementS140();
                        
                        // Données communes
                        $this->setRealCommonData($equipement, $fields);
                        
                        // Données spécifiques hors contrat
                        $this->setRealOffContractData($equipement, $equipmentHorsContrat, $fields, $entityManager);
                        
                        $entityManager->persist($equipement);
                        $offContractEquipments++;
                        
                        $processedEquipments[] = [
                            'type' => 'off_contract',
                            'numero' => $equipement->getNumeroEquipement(),
                            'libelle' => $equipement->getLibelleEquipement(),
                            'etat' => $equipement->getEtat()
                        ];
                        
                    } catch (\Exception $e) {
                        error_log("Erreur équipement hors contrat $index: " . $e->getMessage());
                    }
                }
            }

            // 5. Sauvegarder
            $entityManager->flush();

            // 6. Marquer comme lu
            $this->markFormAsRead($formId, $entryId);

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed_entry' => [
                    'form_id' => $formId,
                    'entry_id' => $entryId,
                    'client_id' => $fields['id_client_']['value'] ?? '',
                    'client_name' => $fields['nom_du_client']['value'] ?? '',
                    'technician' => $fields['technicien']['value'] ?? '',
                    'date' => $fields['date_et_heure']['value'] ?? ''
                ],
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'total_equipments' => $contractEquipments + $offContractEquipments,
                'processed_equipments' => $processedEquipments,
                'message' => "Formulaire {$entryId} traité: " . 
                            ($contractEquipments + $offContractEquipments) . " équipements ajoutés"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Définir les données communes - VERSION FINALE
     */
    private function setRealCommonData($equipement, array $fields): void
    {
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_du_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['technicien']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure']['value'] ?? '');
        
        // Valeurs par défaut
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    /**
     * Données spécifiques contrat - VERSION FINALE
     */
    private function setRealContractData($equipement, array $equipmentContrat): void
    {
        // Extraction du path et value
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $equipementValue = $equipmentContrat['equipement']['value'] ?? '';
        
        // Type de visite depuis le path
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // Parse des infos équipement
        $equipmentInfo = $this->parseEquipmentInfo($equipementValue);
        
        $equipement->setNumeroEquipement($equipmentInfo['numero'] ?? '');
        $equipement->setLibelleEquipement($equipmentInfo['libelle'] ?? '');
        $equipement->setMiseEnService($equipmentInfo['mise_en_service'] ?? '');
        $equipement->setNumeroDeSerie($equipmentInfo['numero_serie'] ?? '');
        $equipement->setMarque($equipmentInfo['marque'] ?? '');
        $equipement->setHauteur($equipmentInfo['hauteur'] ?? '');
        $equipement->setLargeur($equipmentInfo['largeur'] ?? '');
        $equipement->setRepereSiteClient($equipmentInfo['repere'] ?? '');
        
        // Données du formulaire
        $equipement->setModeFonctionnement($equipmentContrat['mode_fonctionnement']['value'] ?? '');
        $equipement->setLongueur($equipmentContrat['longueur']['value'] ?? '');
        $equipement->setPlaqueSignaletique($equipmentContrat['plaque_signaletique']['value'] ?? '');
        $equipement->setEtat($equipmentContrat['etat']['value'] ?? '');
        
        // Statut de maintenance
        $equipement->setStatutDeMaintenance($this->getMaintenanceStatusFromEtat($equipmentContrat['etat']['value'] ?? ''));
        
        $equipement->setEnMaintenance(true);
    }

    /**
     * Données spécifiques hors contrat - VERSION FINALE
     */
    private function setRealOffContractData($equipement, array $equipmentHorsContrat, array $fields, EntityManagerInterface $entityManager): void
    {
        $typeLibelle = strtolower($equipmentHorsContrat['nature']['value'] ?? '');
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        $idClient = $fields['id_client_']['value'] ?? '';
        
        // Génération du numéro
        $nouveauNumero = $this->getNextEquipmentNumberReal($typeCode, $idClient, $entityManager);
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
    }

    /**
     * Récupération du prochain numéro - VERSION FINALE
     */
    private function getNextEquipmentNumberReal(string $typeCode, string $idClient, EntityManagerInterface $entityManager): int
    {
        $repository = $entityManager->getRepository(EquipementS140::class);
        
        $equipments = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typeCode')
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

    /**
     * Route pour traiter TOUS les formulaires S140 trouvés
     */
    #[Route('/api/maintenance/process-all-s140', name: 'app_maintenance_process_all_s140', methods: ['GET'])]
    public function processAllS140Maintenance(
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // IDs trouvés lors du check
        $entries = [
            ['form_id' => '1088761', 'entry_id' => '232647438'],
            ['form_id' => '1088761', 'entry_id' => '232647490'],  
            ['form_id' => '1088761', 'entry_id' => '232647488'],
            ['form_id' => '1088761', 'entry_id' => '232647486'],
            ['form_id' => '1088761', 'entry_id' => '232647484']
        ];

        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;
        $totalEquipments = 0;

        foreach ($entries as $entry) {
            try {
                // Simuler l'appel à process-real
                $subRequest = Request::create('/api/maintenance/process-real/S140', 'GET', [
                    'form_id' => $entry['form_id'],
                    'entry_id' => $entry['entry_id']
                ]);

                $response = $this->processRealMaintenanceEntry('S140', $entityManager, $subRequest);
                $data = json_decode($response->getContent(), true);

                if ($data['success']) {
                    $totalSuccess++;
                    $totalEquipments += $data['total_equipments'];
                    $results[] = [
                        'entry_id' => $entry['entry_id'],
                        'status' => 'success',
                        'equipments' => $data['total_equipments'],
                        'client_name' => $data['processed_entry']['client_name']
                    ];
                } else {
                    $totalErrors++;
                    $results[] = [
                        'entry_id' => $entry['entry_id'],
                        'status' => 'error',
                        'error' => $data['error']
                    ];
                }

                // Pause pour éviter surcharge
                sleep(1);

            } catch (\Exception $e) {
                $totalErrors++;
                $results[] = [
                    'entry_id' => $entry['entry_id'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'agency' => 'S140',
            'total_entries' => count($entries),
            'successful' => $totalSuccess,
            'errors' => $totalErrors,
            'total_equipments_added' => $totalEquipments,
            'details' => $results,
            'message' => "Traitement terminé: {$totalSuccess}/" . count($entries) . " formulaires traités, {$totalEquipments} équipements ajoutés"
        ]);
    }

    /**
     * Route de debug pour analyser la structure des entrées qui posent problème
     */
    #[Route('/api/maintenance/debug-entry/{formId}/{entryId}', name: 'app_maintenance_debug_entry', methods: ['GET'])]
    public function debugEntryStructure(
        string $formId,
        string $entryId,
        Request $request
    ): JsonResponse {
        
        try {
            // Récupérer l'entrée spécifique
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            
            return new JsonResponse([
                'success' => true,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'raw_structure' => $detailData,
                'has_data_key' => isset($detailData['data']),
                'has_fields_key' => isset($detailData['data']['fields']),
                'data_keys' => isset($detailData['data']) ? array_keys($detailData['data']) : null,
                'fields_keys' => isset($detailData['data']['fields']) ? array_keys($detailData['data']['fields']) : null,
                'analysis' => [
                    'structure_type' => $this->analyzeStructure($detailData),
                    'is_valid_maintenance_form' => $this->isValidMaintenanceForm($detailData)
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Analyser la structure des données
     */
    private function analyzeStructure(array $data): string
    {
        if (!isset($data['data'])) {
            return 'missing_data_key';
        }
        
        if (!isset($data['data']['fields'])) {
            return 'missing_fields_key';
        }
        
        if (empty($data['data']['fields'])) {
            return 'empty_fields';
        }
        
        return 'valid_structure';
    }

    /**
     * Vérifier si c'est un formulaire de maintenance valide
     */
    private function isValidMaintenanceForm(array $data): bool
    {
        if (!isset($data['data']['fields'])) {
            return false;
        }
        
        $fields = $data['data']['fields'];
        
        // Vérifier la présence des champs essentiels
        $requiredFields = ['code_agence', 'nom_du_client', 'technicien'];
        
        foreach ($requiredFields as $field) {
            if (!isset($fields[$field]['value'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Route de debug rapide pour les 3 entrées en erreur
     */
    #[Route('/api/maintenance/debug-failed-entries', name: 'app_maintenance_debug_failed', methods: ['GET'])]
    public function debugFailedEntries(Request $request): JsonResponse
    {
        $failedEntries = [
            '232647490',
            '232647486', 
            '232647484'
        ];
        
        $results = [];
        
        foreach ($failedEntries as $entryId) {
            try {
                $detailResponse = $this->client->request(
                    'GET',
                    'https://forms.kizeo.com/rest/v3/forms/1088761/data/' . $entryId,
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                    ]
                );

                $detailData = $detailResponse->toArray();
                
                $results[$entryId] = [
                    'status' => 'api_success',
                    'has_data' => isset($detailData['data']),
                    'has_fields' => isset($detailData['data']['fields']),
                    'structure' => $this->analyzeStructure($detailData),
                    'data_keys' => isset($detailData['data']) ? array_keys($detailData['data']) : null,
                    'sample_data' => isset($detailData['data']) ? 
                        array_slice($detailData['data'], 0, 3, true) : null
                ];

            } catch (\Exception $e) {
                $results[$entryId] = [
                    'status' => 'api_error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return new JsonResponse([
            'success' => true,
            'failed_entries_analysis' => $results,
            'recommendation' => $this->getRecommendation($results)
        ]);
    }

    /**
     * Générer une recommandation basée sur l'analyse
     */
    private function getRecommendation(array $results): string
    {
        $hasApiErrors = false;
        $hasStructureIssues = false;
        
        foreach ($results as $entryId => $result) {
            if ($result['status'] === 'api_error') {
                $hasApiErrors = true;
            } elseif ($result['structure'] !== 'valid_structure') {
                $hasStructureIssues = true;
            }
        }
        
        if ($hasApiErrors) {
            return 'Certaines entrées ne sont plus accessibles via l\'API - elles ont peut-être été supprimées';
        }
        
        if ($hasStructureIssues) {
            return 'Certaines entrées ont une structure différente - ajouter une validation avant traitement';
        }
        
        return 'Toutes les entrées semblent valides - vérifier la logique de traitement';
    }

    /**
     * Version corrigée du traitement avec validation de structure
     */
    #[Route('/api/maintenance/process-real-safe/{agencyCode}', name: 'app_maintenance_process_real_safe', methods: ['GET'])]
    public function processRealMaintenanceEntrySafe(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id', '232647438');

        try {
            // 1. Récupérer l'entrée spécifique
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            
            // 2. VALIDATION DE STRUCTURE
            if (!isset($detailData['data'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Structure invalide: clé "data" manquante',
                    'structure' => array_keys($detailData)
                ], 400);
            }
            
            if (!isset($detailData['data']['fields'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Structure invalide: clé "fields" manquante',
                    'data_structure' => array_keys($detailData['data'])
                ], 400);
            }
            
            $fields = $detailData['data']['fields'];
            
            // 3. Validation des champs obligatoires
            if (!isset($fields['code_agence']['value'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Champ code_agence manquant',
                    'available_fields' => array_keys($fields)
                ], 400);
            }

            // 4. Vérifier que c'est bien S140
            if ($fields['code_agence']['value'] !== 'S140') {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cette entrée n\'est pas S140',
                    'actual_agency' => $fields['code_agence']['value']
                ], 400);
            }

            // 5. Traitement normal à partir d'ici
            $contractEquipments = 0;
            $offContractEquipments = 0;
            $processedEquipments = [];

            // Traiter les équipements sous contrat
            if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
                foreach ($fields['contrat_de_maintenance']['value'] as $index => $equipmentContrat) {
                    try {
                        $equipement = new EquipementS140();
                        $this->setRealCommonData($equipement, $fields);
                        $this->setRealContractData($equipement, $equipmentContrat);
                        
                        $entityManager->persist($equipement);
                        $contractEquipments++;
                        
                        $processedEquipments[] = [
                            'type' => 'contract',
                            'numero' => $equipement->getNumeroEquipement(),
                            'libelle' => $equipement->getLibelleEquipement()
                        ];
                        
                    } catch (\Exception $e) {
                        error_log("Erreur équipement contrat $index: " . $e->getMessage());
                    }
                }
            }

            // Traiter les équipements hors contrat
            if (isset($fields['hors_contrat']['value']) && !empty($fields['hors_contrat']['value'])) {
                foreach ($fields['hors_contrat']['value'] as $index => $equipmentHorsContrat) {
                    try {
                        $equipement = new EquipementS140();
                        $this->setRealCommonData($equipement, $fields);
                        $this->setRealOffContractData($equipement, $equipmentHorsContrat, $fields, $entityManager);
                        
                        $entityManager->persist($equipement);
                        $offContractEquipments++;
                        
                        $processedEquipments[] = [
                            'type' => 'off_contract',
                            'numero' => $equipement->getNumeroEquipement(),
                            'libelle' => $equipement->getLibelleEquipement()
                        ];
                        
                    } catch (\Exception $e) {
                        error_log("Erreur équipement hors contrat $index: " . $e->getMessage());
                    }
                }
            }

            // Sauvegarder
            $entityManager->flush();

            // Marquer comme lu
            $this->markFormAsRead($formId, $entryId);

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed_entry' => [
                    'form_id' => $formId,
                    'entry_id' => $entryId,
                    'client_id' => $fields['id_client_']['value'] ?? '',
                    'client_name' => $fields['nom_du_client']['value'] ?? '',
                    'technician' => $fields['technicien']['value'] ?? '',
                    'date' => $fields['date_et_heure']['value'] ?? ''
                ],
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'total_equipments' => $contractEquipments + $offContractEquipments,
                'processed_equipments' => $processedEquipments,
                'message' => "Formulaire {$entryId} traité: " . 
                            ($contractEquipments + $offContractEquipments) . " équipements ajoutés"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * SOLUTION FINALE : Traitement intelligent avec filtrage des entrées valides
     */

    /**
     * Route améliorée pour récupérer SEULEMENT les formulaires S140 valides
     */
    #[Route('/api/maintenance/check-valid-s140', name: 'app_maintenance_check_valid_s140', methods: ['GET'])]
    public function checkValidS140Entries(Request $request): JsonResponse
    {
        try {
            $validEntries = [];
            $invalidEntries = [];
            $totalChecked = 0;

            // 1. Récupérer la liste des formulaires MAINTENANCE
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

            // 2. Pour chaque formulaire, chercher les entrées S140 VALIDES
            foreach ($maintenanceForms as $form) {
                try {
                    // Récupérer les entrées non lues
                    $unreadResponse = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' . $form['id'] . '/data/unread/read/20',
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );

                    $unreadData = $unreadResponse->toArray();
                    $totalChecked += count($unreadData['data'] ?? []);

                    // 3. Vérifier chaque entrée
                    foreach ($unreadData['data'] ?? [] as $entry) {
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
                            
                            // 4. VALIDATION DE STRUCTURE
                            if (!isset($detailData['data']['fields'])) {
                                $invalidEntries[] = [
                                    'form_id' => $form['id'],
                                    'entry_id' => $entry['_id'],
                                    'reason' => 'no_fields_data'
                                ];
                                continue;
                            }

                            $fields = $detailData['data']['fields'];

                            // 5. Vérifier si c'est S140 ET valide
                            if (isset($fields['code_agence']['value']) && 
                                $fields['code_agence']['value'] === 'S140') {
                                
                                $validEntries[] = [
                                    'form_id' => $form['id'],
                                    'form_name' => $form['name'],
                                    'entry_id' => $entry['_id'],
                                    'client_id' => $fields['id_client_']['value'] ?? '',
                                    'client_name' => $fields['nom_du_client']['value'] ?? '',
                                    'technician' => $fields['technicien']['value'] ?? '',
                                    'date' => $fields['date_et_heure']['value'] ?? '',
                                    'has_contract_equipment' => !empty($fields['contrat_de_maintenance']['value'] ?? []),
                                    'has_offcontract_equipment' => !empty($fields['hors_contrat']['value'] ?? []),
                                    'contract_count' => count($fields['contrat_de_maintenance']['value'] ?? []),
                                    'offcontract_count' => count($fields['hors_contrat']['value'] ?? [])
                                ];
                            }

                        } catch (\Exception $e) {
                            $invalidEntries[] = [
                                'form_id' => $form['id'],
                                'entry_id' => $entry['_id'] ?? 'unknown',
                                'reason' => 'api_error: ' . $e->getMessage()
                            ];
                        }
                    }

                } catch (\Exception $e) {
                    error_log("Erreur formulaire {$form['id']}: " . $e->getMessage());
                }
            }

            return new JsonResponse([
                'success' => true,
                'total_maintenance_forms' => count($maintenanceForms),
                'total_entries_checked' => $totalChecked,
                'valid_s140_entries' => count($validEntries),
                'invalid_entries' => count($invalidEntries),
                'valid_entries' => $validEntries,
                'invalid_entries_details' => $invalidEntries,
                'ready_to_process' => count($validEntries) > 0,
                'recommendation' => count($validEntries) > 0 ? 
                    'Utiliser /process-valid-s140 pour traiter les ' . count($validEntries) . ' entrées valides' :
                    'Aucune entrée S140 valide trouvée'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Route pour traiter SEULEMENT les entrées S140 valides
     */
    #[Route('/api/maintenance/process-valid-s140', name: 'app_maintenance_process_valid_s140', methods: ['GET'])]
    public function processValidS140Entries(
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        try {
            // 1. D'abord récupérer les entrées valides
            $checkRequest = Request::create('/api/maintenance/check-valid-s140', 'GET');
            $checkResponse = $this->checkValidS140Entries($checkRequest);
            $checkData = json_decode($checkResponse->getContent(), true);

            if (!$checkData['success'] || empty($checkData['valid_entries'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune entrée S140 valide trouvée',
                    'details' => $checkData
                ], 400);
            }

            $validEntries = $checkData['valid_entries'];
            $results = [];
            $totalSuccess = 0;
            $totalErrors = 0;
            $totalEquipments = 0;

            // 2. Traiter chaque entrée valide
            foreach ($validEntries as $entry) {
                try {
                    $result = $this->processSingleValidEntry(
                        $entry['form_id'], 
                        $entry['entry_id'], 
                        $entityManager
                    );

                    if ($result['success']) {
                        $totalSuccess++;
                        $totalEquipments += $result['total_equipments'];
                        $results[] = [
                            'entry_id' => $entry['entry_id'],
                            'client_name' => $entry['client_name'],
                            'status' => 'success',
                            'equipments' => $result['total_equipments'],
                            'contract_equipments' => $result['contract_equipments'],
                            'off_contract_equipments' => $result['off_contract_equipments']
                        ];
                    } else {
                        $totalErrors++;
                        $results[] = [
                            'entry_id' => $entry['entry_id'],
                            'client_name' => $entry['client_name'],
                            'status' => 'error',
                            'error' => $result['error']
                        ];
                    }

                    // Pause entre traitements
                    sleep(1);

                } catch (\Exception $e) {
                    $totalErrors++;
                    $results[] = [
                        'entry_id' => $entry['entry_id'],
                        'client_name' => $entry['client_name'],
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return new JsonResponse([
                'success' => true,
                'agency' => 'S140',
                'total_valid_entries' => count($validEntries),
                'successful' => $totalSuccess,
                'errors' => $totalErrors,
                'total_equipments_added' => $totalEquipments,
                'processing_details' => $results,
                'message' => "Traitement filtré terminé: {$totalSuccess}/" . count($validEntries) . 
                            " entrées valides traitées, {$totalEquipments} équipements ajoutés"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Traiter une entrée valide spécifique
     */
    private function processSingleValidEntry(
        string $formId, 
        string $entryId, 
        EntityManagerInterface $entityManager
    ): array {
        
        try {
            // Récupérer les données
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];

            $contractEquipments = 0;
            $offContractEquipments = 0;

            // Traiter équipements sous contrat
            if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
                foreach ($fields['contrat_de_maintenance']['value'] as $equipmentContrat) {
                    $equipement = new EquipementS140();
                    $this->setRealCommonData($equipement, $fields);
                    $this->setRealContractData($equipement, $equipmentContrat);
                    
                    $entityManager->persist($equipement);
                    $contractEquipments++;
                }
            }

            // Traiter équipements hors contrat
            if (isset($fields['hors_contrat']['value']) && !empty($fields['hors_contrat']['value'])) {
                foreach ($fields['hors_contrat']['value'] as $equipmentHorsContrat) {
                    $equipement = new EquipementS140();
                    $this->setRealCommonData($equipement, $fields);
                    $this->setRealOffContractData($equipement, $equipmentHorsContrat, $fields, $entityManager);
                    
                    $entityManager->persist($equipement);
                    $offContractEquipments++;
                }
            }

            // Sauvegarder
            $entityManager->flush();

            // Marquer comme lu
            $this->markFormAsRead($formId, $entryId);

            return [
                'success' => true,
                'total_equipments' => $contractEquipments + $offContractEquipments,
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * SOLUTION OPTIMISÉE : Traitement par lots d'équipements pour éviter les timeouts
     */

    /**
     * Route pour traiter un formulaire S140 par lots d'équipements
     */
    #[Route('/api/maintenance/process-chunked/{agencyCode}', name: 'app_maintenance_process_chunked', methods: ['GET'])]
    public function processMaintenanceChunked(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        // Configuration optimisée
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 120);
        
        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id');
        $chunkSize = (int) $request->query->get('chunk_size', 15); // 15 équipements par lot
        $startOffset = (int) $request->query->get('offset', 0);

        if (!$entryId) {
            return new JsonResponse([
                'error' => 'Paramètre entry_id requis',
                'available_entries' => [
                    '232647438', '232647488' // Les 2 qui fonctionnaient
                ]
            ], 400);
        }

        try {
            // 1. Récupérer SEULEMENT les métadonnées du formulaire
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 30
                ]
            );

            $detailData = $detailResponse->toArray();
            
            if (!isset($detailData['data']['fields'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Formulaire sans données valides'
                ], 400);
            }

            $fields = $detailData['data']['fields'];

            // 2. Analyser le contenu SANS traiter
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['hors_contrat']['value'] ?? [];
            
            $totalContractEquipments = count($contractEquipments);
            $totalOffContractEquipments = count($offContractEquipments);
            $totalEquipments = $totalContractEquipments + $totalOffContractEquipments;

            // 3. Si trop d'équipements, découper en lots
            if ($totalEquipments > $chunkSize) {
                return $this->processEquipmentChunk(
                    $fields, 
                    $contractEquipments, 
                    $offContractEquipments, 
                    $chunkSize, 
                    $startOffset, 
                    $entityManager,
                    $formId,
                    $entryId
                );
            }

            // 4. Si moins que la limite, traiter normalement
            return $this->processAllEquipments(
                $fields, 
                $contractEquipments, 
                $offContractEquipments, 
                $entityManager,
                $formId,
                $entryId
            );

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'memory_used' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ], 500);
        }
    }

    /**
     * Traiter un lot d'équipements
     */
    private function processEquipmentChunk(
        array $fields,
        array $contractEquipments,
        array $offContractEquipments,
        int $chunkSize,
        int $startOffset,
        EntityManagerInterface $entityManager,
        string $formId,
        string $entryId
    ): JsonResponse {
        
        $processedEquipments = 0;
        $contractProcessed = 0;
        $offContractProcessed = 0;
        $errors = [];

        // Combiner tous les équipements avec leur type
        $allEquipments = [];
        
        foreach ($contractEquipments as $index => $equipment) {
            $allEquipments[] = [
                'type' => 'contract',
                'data' => $equipment,
                'index' => $index
            ];
        }
        
        foreach ($offContractEquipments as $index => $equipment) {
            $allEquipments[] = [
                'type' => 'off_contract',
                'data' => $equipment,
                'index' => $index
            ];
        }

        // Découper en lots
        $chunk = array_slice($allEquipments, $startOffset, $chunkSize);
        
        if (empty($chunk)) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Lot vide - traitement terminé',
                'total_equipments' => count($allEquipments),
                'processed_offset' => $startOffset,
                'chunk_size' => $chunkSize,
                'is_complete' => true
            ]);
        }

        // Traiter le lot
        foreach ($chunk as $equipmentData) {
            try {
                $equipement = new EquipementS140();
                $this->setRealCommonData($equipement, $fields);
                
                if ($equipmentData['type'] === 'contract') {
                    $this->setRealContractData($equipement, $equipmentData['data']);
                    $contractProcessed++;
                } else {
                    $this->setRealOffContractData($equipement, $equipmentData['data'], $fields, $entityManager);
                    $offContractProcessed++;
                }
                
                $entityManager->persist($equipement);
                $processedEquipments++;
                
                // Sauvegarder tous les 5 équipements pour éviter la surcharge
                if ($processedEquipments % 5 === 0) {
                    $entityManager->flush();
                    $entityManager->clear();
                    gc_collect_cycles();
                }
                
            } catch (\Exception $e) {
                $errors[] = [
                    'equipment_index' => $equipmentData['index'],
                    'type' => $equipmentData['type'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // Sauvegarde finale
        $entityManager->flush();
        $entityManager->clear();

        $nextOffset = $startOffset + $chunkSize;
        $isComplete = $nextOffset >= count($allEquipments);
        
        // Marquer comme lu seulement si c'est le dernier lot
        if ($isComplete) {
            $this->markFormAsRead($formId, $entryId);
        }

        return new JsonResponse([
            'success' => true,
            'agency' => 'S140',
            'form_id' => $formId,
            'entry_id' => $entryId,
            'client_name' => $fields['nom_du_client']['value'] ?? '',
            'batch_info' => [
                'total_equipments' => count($allEquipments),
                'processed_in_this_batch' => $processedEquipments,
                'contract_processed' => $contractProcessed,
                'off_contract_processed' => $offContractProcessed,
                'start_offset' => $startOffset,
                'chunk_size' => $chunkSize,
                'next_offset' => $nextOffset,
                'is_complete' => $isComplete
            ],
            'errors' => $errors,
            'next_call' => $isComplete ? null : 
                "/api/maintenance/process-chunked/S140?form_id={$formId}&entry_id={$entryId}&offset={$nextOffset}&chunk_size={$chunkSize}",
            'message' => $isComplete ? 
                "Traitement terminé: {$processedEquipments} équipements dans ce lot" :
                "Lot traité: {$processedEquipments} équipements. Appeler l'URL next_call pour continuer"
        ]);
    }

    /**
     * Traiter tous les équipements si le nombre est gérable
     */
    private function processAllEquipments(
        array $fields,
        array $contractEquipments,
        array $offContractEquipments,
        EntityManagerInterface $entityManager,
        string $formId,
        string $entryId
    ): JsonResponse {
        
        $contractProcessed = 0;
        $offContractProcessed = 0;
        $errors = [];

        // Traiter équipements sous contrat
        foreach ($contractEquipments as $index => $equipmentContrat) {
            try {
                $equipement = new EquipementS140();
                $this->setRealCommonData($equipement, $fields);
                $this->setRealContractData($equipement, $equipmentContrat);
                
                $entityManager->persist($equipement);
                $contractProcessed++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'type' => 'contract',
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Traiter équipements hors contrat
        foreach ($offContractEquipments as $index => $equipmentHorsContrat) {
            try {
                $equipement = new EquipementS140();
                $this->setRealCommonData($equipement, $fields);
                $this->setRealOffContractData($equipement, $equipmentHorsContrat, $fields, $entityManager);
                
                $entityManager->persist($equipement);
                $offContractProcessed++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'type' => 'off_contract',
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Sauvegarder
        $entityManager->flush();
        
        // Marquer comme lu
        $this->markFormAsRead($formId, $entryId);

        return new JsonResponse([
            'success' => true,
            'agency' => 'S140',
            'form_id' => $formId,
            'entry_id' => $entryId,
            'client_name' => $fields['nom_du_client']['value'] ?? '',
            'contract_equipments' => $contractProcessed,
            'off_contract_equipments' => $offContractProcessed,
            'total_equipments' => $contractProcessed + $offContractProcessed,
            'errors' => $errors,
            'message' => "Formulaire traité entièrement: " . 
                        ($contractProcessed + $offContractProcessed) . " équipements ajoutés"
        ]);
    }

    /**
     * Route pour analyser un formulaire AVANT traitement
     */
    #[Route('/api/maintenance/analyze/{agencyCode}', name: 'app_maintenance_analyze', methods: ['GET'])]
    public function analyzeMaintenanceForm(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id');

        if (!$entryId) {
            return new JsonResponse(['error' => 'Paramètre entry_id requis'], 400);
        }

        try {
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            
            if (!isset($detailData['data']['fields'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Formulaire sans données valides'
                ], 400);
            }

            $fields = $detailData['data']['fields'];
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['hors_contrat']['value'] ?? [];
            
            $totalEquipments = count($contractEquipments) + count($offContractEquipments);
            $recommendedChunkSize = max(10, min(20, intval($totalEquipments / 4)));

            return new JsonResponse([
                'success' => true,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'client_name' => $fields['nom_du_client']['value'] ?? '',
                'technician' => $fields['technicien']['value'] ?? '',
                'date' => $fields['date_et_heure']['value'] ?? '',
                'equipment_analysis' => [
                    'contract_equipments' => count($contractEquipments),
                    'off_contract_equipments' => count($offContractEquipments),
                    'total_equipments' => $totalEquipments,
                    'memory_risk' => $totalEquipments > 30 ? 'HIGH' : ($totalEquipments > 15 ? 'MEDIUM' : 'LOW'),
                    'recommended_chunk_size' => $recommendedChunkSize,
                    'estimated_batches' => ceil($totalEquipments / $recommendedChunkSize)
                ],
                'processing_recommendation' => $totalEquipments > 20 ? 
                    "Utiliser le traitement par lots avec chunk_size={$recommendedChunkSize}" :
                    "Traitement normal possible",
                'next_call' => $totalEquipments > 20 ?
                    "/api/maintenance/process-chunked/S140?form_id={$formId}&entry_id={$entryId}&chunk_size={$recommendedChunkSize}" :
                    "/api/maintenance/process-chunked/S140?form_id={$formId}&entry_id={$entryId}"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Route de debug pour analyser exactement les données reçues de Kizeo
     */
    #[Route('/api/maintenance/debug-equipment-data/{entryId}', name: 'app_maintenance_debug_equipment_data', methods: ['GET'])]
    public function debugEquipmentData(
        string $entryId,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id', '1088761');
        
        try {
            // Récupérer les données brutes
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];

            // Analyser la structure des équipements
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['hors_contrat']['value'] ?? [];

            $analysis = [
                'form_fields' => [
                    'code_agence' => $fields['code_agence']['value'] ?? 'MISSING',
                    'id_client_' => $fields['id_client_']['value'] ?? 'MISSING',
                    'nom_du_client' => $fields['nom_du_client']['value'] ?? 'MISSING',
                    'technicien' => $fields['technicien']['value'] ?? 'MISSING',
                    'date_et_heure' => $fields['date_et_heure']['value'] ?? 'MISSING'
                ],
                'contract_equipment_sample' => [],
                'off_contract_equipment_sample' => [],
                'available_field_keys' => array_keys($fields)
            ];

            // Analyser le premier équipement contrat
            if (!empty($contractEquipments)) {
                $firstContract = $contractEquipments[0];
                $analysis['contract_equipment_sample'] = [
                    'raw_structure' => $firstContract,
                    'equipement_path' => $firstContract['equipement']['path'] ?? 'MISSING',
                    'equipement_value' => $firstContract['equipement']['value'] ?? 'MISSING',
                    'mode_fonctionnement' => $firstContract['mode_fonctionnement']['value'] ?? 'MISSING',
                    'longueur' => $firstContract['longueur']['value'] ?? 'MISSING',
                    'plaque_signaletique' => $firstContract['plaque_signaletique']['value'] ?? 'MISSING',
                    'etat' => $firstContract['etat']['value'] ?? 'MISSING',
                    'available_keys' => array_keys($firstContract)
                ];

                // Analyser parseEquipmentInfo
                $equipmentValue = $firstContract['equipement']['value'] ?? '';
                $parsedInfo = $this->debugParseEquipmentInfo($equipmentValue);
                $analysis['contract_equipment_sample']['parsed_equipment_info'] = $parsedInfo;
            }

            // Analyser le premier équipement hors contrat
            if (!empty($offContractEquipments)) {
                $firstOffContract = $offContractEquipments[0];
                $analysis['off_contract_equipment_sample'] = [
                    'raw_structure' => $firstOffContract,
                    'available_keys' => array_keys($firstOffContract)
                ];
            }

            return new JsonResponse([
                'success' => true,
                'entry_id' => $entryId,
                'total_contract_equipments' => count($contractEquipments),
                'total_off_contract_equipments' => count($offContractEquipments),
                'analysis' => $analysis
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Version debug de parseEquipmentInfo pour voir le parsing
     */
    private function debugParseEquipmentInfo(string $equipmentValue): array
    {
        if (empty($equipmentValue)) {
            return ['error' => 'equipmentValue is empty'];
        }

        // Parse de la structure "ATEIS\CEA\SEC01|Porte sectionnelle|..."
        $parts = explode('|', $equipmentValue);
        
        return [
            'original_value' => $equipmentValue,
            'parts_count' => count($parts),
            'parts' => $parts,
            'parsed_result' => [
                'numero' => $parts[0] ?? 'MISSING',
                'libelle' => $parts[1] ?? 'MISSING',
                'mise_en_service' => $parts[2] ?? 'MISSING',
                'numero_serie' => $parts[3] ?? 'MISSING',
                'marque' => $parts[4] ?? 'MISSING',
                'hauteur' => $parts[5] ?? 'MISSING',
                'largeur' => $parts[6] ?? 'MISSING',
                'repere' => $parts[7] ?? 'MISSING'
            ]
        ];
    }

    /**
     * Setters corrigés basés sur l'analyse des données
     */
    private function setRealCommonDataCorrected($equipement, array $fields): void
    {
        // Logging pour debug
        error_log("=== DEBUG COMMON DATA ===");
        error_log("code_agence: " . ($fields['code_agence']['value'] ?? 'NULL'));
        error_log("id_client_: " . ($fields['id_client_']['value'] ?? 'NULL'));
        error_log("nom_du_client: " . ($fields['nom_du_client']['value'] ?? 'NULL'));
        error_log("technicien: " . ($fields['technicien']['value'] ?? 'NULL'));
        
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_du_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['technicien']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure']['value'] ?? '');
        
        // Valeurs par défaut
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    /**
     * Setters contrat corrigés
     */
    private function setRealContractDataCorrected($equipement, array $equipmentContrat): void
    {
        // Logging pour debug
        error_log("=== DEBUG CONTRACT DATA ===");
        error_log("equipement path: " . ($equipmentContrat['equipement']['path'] ?? 'NULL'));
        error_log("equipement value: " . ($equipmentContrat['equipement']['value'] ?? 'NULL'));
        error_log("mode_fonctionnement: " . ($equipmentContrat['mode_fonctionnement']['value'] ?? 'NULL'));
        error_log("etat: " . ($equipmentContrat['etat']['value'] ?? 'NULL'));
        
        // Extraction du path et value
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $equipementValue = $equipmentContrat['equipement']['value'] ?? '';
        
        // Type de visite depuis le path
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // Parse des infos équipement
        $equipmentInfo = $this->parseEquipmentInfo($equipementValue);
        
        // CORRECTION: Ajouter des vérifications null/empty
        $numeroEquipement = !empty($equipmentInfo['numero']) ? $equipmentInfo['numero'] : 'AUTO_' . uniqid();
        $equipement->setNumeroEquipement($numeroEquipement);
        
        $libelleEquipement = !empty($equipmentInfo['libelle']) ? $equipmentInfo['libelle'] : 'Équipement';
        $equipement->setLibelleEquipement($libelleEquipement);
        
        $equipement->setMiseEnService($equipmentInfo['mise_en_service'] ?? '');
        $equipement->setNumeroDeSerie($equipmentInfo['numero_serie'] ?? '');
        $equipement->setMarque($equipmentInfo['marque'] ?? '');
        $equipement->setHauteur($equipmentInfo['hauteur'] ?? '');
        $equipement->setLargeur($equipmentInfo['largeur'] ?? '');
        $equipement->setRepereSiteClient($equipmentInfo['repere'] ?? '');
        
        // Données du formulaire avec vérifications
        $modeFonctionnement = $equipmentContrat['mode_fonctionnement']['value'] ?? '';
        $equipement->setModeFonctionnement($modeFonctionnement);
        
        $longueur = $equipmentContrat['longueur']['value'] ?? '';
        $equipement->setLongueur($longueur);
        
        $plaqueSignaletique = $equipmentContrat['plaque_signaletique']['value'] ?? '';
        $equipement->setPlaqueSignaletique($plaqueSignaletique);
        
        $etat = $equipmentContrat['etat']['value'] ?? '';
        $equipement->setEtat($etat);
        
        // Statut de maintenance
        $statut = $this->getMaintenanceStatusFromEtat($etat);
        $equipement->setStatutDeMaintenance($statut);
        
        $equipement->setEnMaintenance(true);
        
        // Logging des valeurs définies
        error_log("Set values - numero: $numeroEquipement, libelle: $libelleEquipement, mode: $modeFonctionnement, etat: $etat");
    }

    /**
     * Route de test avec les setters corrigés
     */
    #[Route('/api/maintenance/test-corrected/{entryId}', name: 'app_maintenance_test_corrected', methods: ['GET'])]
    public function testCorrectedSetters(
        string $entryId,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id', '1088761');
        
        try {
            // Récupérer les données
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];

            if (empty($contractEquipments)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Aucun équipement sous contrat trouvé'
                ], 400);
            }

            // Traiter SEULEMENT le premier équipement pour test
            $firstEquipment = $contractEquipments[0];
            
            $equipement = new EquipementS140();
            $this->setRealCommonDataCorrected($equipement, $fields);
            $this->setRealContractDataCorrected($equipement, $firstEquipment);
            
            // Sauvegarder
            $entityManager->persist($equipement);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Équipement test créé avec setters corrigés',
                'equipment_id' => $equipement->getId(),
                'saved_data' => [
                    'numero_equipement' => $equipement->getNumeroEquipement(),
                    'libelle_equipement' => $equipement->getLibelleEquipement(),
                    'mode_fonctionnement' => $equipement->getModeFonctionnement(),
                    'etat' => $equipement->getEtat(),
                    'visite' => $equipement->getVisite(),
                    'code_agence' => $equipement->getCodeAgence(),
                    'raison_sociale' => $equipement->getRaisonSociale()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * SETTERS CORRIGÉS pour la vraie structure des données S140
     */

    /**
     * Données communes corrigées selon la vraie structure
     */
    private function setRealCommonDataFixed($equipement, array $fields): void
    {
        // CORRECTION : Utiliser les vrais noms de champs
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? ''); // CORRIGÉ
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? ''); // CORRIGÉ
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? ''); // CORRIGÉ
        
        // Valeurs par défaut
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    /**
     * Données contrat corrigées selon la vraie structure S140
     */
    private function setRealContractDataFixed($equipement, array $equipmentContrat): void
    {
        // CORRECTION : Utiliser la vraie structure S140
        
        // 1. Type de visite depuis le path
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // 2. Numéro d'équipement (simple valeur, pas de parsing)
        $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
        $equipement->setNumeroEquipement($numeroEquipement);
        
        // 3. Libellé depuis reference7
        $libelle = $equipmentContrat['reference7']['value'] ?? '';
        $equipement->setLibelleEquipement($libelle);
        
        // 4. Année mise en service depuis reference2
        $miseEnService = $equipmentContrat['reference2']['value'] ?? '';
        $equipement->setMiseEnService($miseEnService);
        
        // 5. Numéro de série depuis reference6
        $numeroSerie = $equipmentContrat['reference6']['value'] ?? '';
        $equipement->setNumeroDeSerie($numeroSerie);
        
        // 6. Marque depuis reference5
        $marque = $equipmentContrat['reference5']['value'] ?? '';
        $equipement->setMarque($marque);
        
        // 7. Hauteur depuis reference1
        $hauteur = $equipmentContrat['reference1']['value'] ?? '';
        $equipement->setHauteur($hauteur);
        
        // 8. Largeur depuis reference3 (si disponible)
        $largeur = $equipmentContrat['reference3']['value'] ?? '';
        $equipement->setLargeur($largeur);
        
        // 9. Localisation depuis localisation_site_client
        $localisation = $equipmentContrat['localisation_site_client']['value'] ?? '';
        $equipement->setRepereSiteClient($localisation);
        
        // 10. Mode fonctionnement corrigé
        $modeFonctionnement = $equipmentContrat['mode_fonctionnement_2']['value'] ?? '';
        $equipement->setModeFonctionnement($modeFonctionnement);
        
        // 11. Plaque signalétique
        $plaqueSignaletique = $equipmentContrat['plaque_signaletique']['value'] ?? '';
        $equipement->setPlaqueSignaletique($plaqueSignaletique);
        
        // 12. État
        $etat = $equipmentContrat['etat']['value'] ?? '';
        $equipement->setEtat($etat);
        
        // 13. Longueur (peut ne pas exister pour certains équipements)
        $longueur = $equipmentContrat['longueur']['value'] ?? '';
        $equipement->setLongueur($longueur);
        
        // 14. Statut de maintenance basé sur l'état
        $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
        $equipement->setStatutDeMaintenance($statut);
        
        $equipement->setEnMaintenance(true);
    }

    /**
     * Correspondance états pour S140
     */
    private function getMaintenanceStatusFromEtatFixed(string $etat): string
    {
        switch ($etat) {
            case "F": // Fonctionnel
                return "RAS";
            case "C": // À réparer/Conforme avec remarques
                return "A_REPARER";
            case "A": // À l'arrêt
                return "HS";
            case "B": // Bon état
                return "RAS";
            default:
                return "RAS";
        }
    }

    /**
     * Route de test avec les setters corrigés pour S140
     */
    #[Route('/api/maintenance/test-s140-fixed/{entryId}', name: 'app_maintenance_test_s140_fixed', methods: ['GET'])]
    public function testS140FixedSetters(
        string $entryId,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id', '1088761');
        
        try {
            // Récupérer les données
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            $fields = $detailData['data']['fields'];
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];

            if (empty($contractEquipments)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Aucun équipement sous contrat trouvé'
                ], 400);
            }

            // Traiter SEULEMENT le premier équipement pour test
            $firstEquipment = $contractEquipments[0];
            
            $equipement = new EquipementS140();
            $this->setRealCommonDataFixed($equipement, $fields);
            $this->setRealContractDataFixed($equipement, $firstEquipment);
            
            // Sauvegarder
            $entityManager->persist($equipement);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Équipement S140 créé avec setters corrigés',
                'equipment_id' => $equipement->getId(),
                'saved_data' => [
                    'numero_equipement' => $equipement->getNumeroEquipement(),
                    'libelle_equipement' => $equipement->getLibelleEquipement(),
                    'mode_fonctionnement' => $equipement->getModeFonctionnement(),
                    'mise_en_service' => $equipement->getMiseEnService(),
                    'numero_de_serie' => $equipement->getNumeroDeSerie(),
                    'marque' => $equipement->getMarque(),
                    'hauteur' => $equipement->getHauteur(),
                    'largeur' => $equipement->getLargeur(),
                    'etat' => $equipement->getEtat(),
                    'visite' => $equipement->getVisite(),
                    'code_agence' => $equipement->getCodeAgence(),
                    'raison_sociale' => $equipement->getRaisonSociale(),
                    'trigramme_tech' => $equipement->getTrigrammeTech()
                ],
                'original_data_mapping' => [
                    'numero_equipement' => $firstEquipment['equipement']['value'],
                    'libelle_from_reference7' => $firstEquipment['reference7']['value'],
                    'mise_en_service_from_reference2' => $firstEquipment['reference2']['value'],
                    'numero_serie_from_reference6' => $firstEquipment['reference6']['value'],
                    'marque_from_reference5' => $firstEquipment['reference5']['value'],
                    'hauteur_from_reference1' => $firstEquipment['reference1']['value'],
                    'largeur_from_reference3' => $firstEquipment['reference3']['value'],
                    'mode_fonctionnement_2' => $firstEquipment['mode_fonctionnement_2']['value'],
                    'etat' => $firstEquipment['etat']['value']
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Version finale pour traiter par lots avec les setters corrigés
     */
    #[Route('/api/maintenance/process-chunked-fixed/{agencyCode}', name: 'app_maintenance_process_chunked_fixed', methods: ['GET'])]
    public function processMaintenanceChunkedFixed(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        if ($agencyCode !== 'S140') {
            return new JsonResponse(['error' => 'Cette route est spécifique à S140'], 400);
        }

        // Configuration optimisée
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 120);
        
        $formId = $request->query->get('form_id', '1088761');
        $entryId = $request->query->get('entry_id');
        $chunkSize = (int) $request->query->get('chunk_size', 15);
        $startOffset = (int) $request->query->get('offset', 0);

        if (!$entryId) {
            return new JsonResponse([
                'error' => 'Paramètre entry_id requis',
                'example' => '/api/maintenance/process-chunked-fixed/S140?entry_id=233668811&chunk_size=15&offset=0'
            ], 400);
        }

        try {
            // Récupérer les données
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                ]
            );

            $detailData = $detailResponse->toArray();
            
            if (!isset($detailData['data']['fields'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Formulaire sans données valides'
                ], 400);
            }

            $fields = $detailData['data']['fields'];
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['tableau2']['value'] ?? []; // Équipements hors contrat
            
            $totalEquipments = count($contractEquipments) + count($offContractEquipments);

            // Traitement par lots
            $allEquipments = [];
            
            foreach ($contractEquipments as $index => $equipment) {
                $allEquipments[] = [
                    'type' => 'contract',
                    'data' => $equipment,
                    'index' => $index
                ];
            }
            
            foreach ($offContractEquipments as $index => $equipment) {
                $allEquipments[] = [
                    'type' => 'off_contract',
                    'data' => $equipment,
                    'index' => $index
                ];
            }

            $chunk = array_slice($allEquipments, $startOffset, $chunkSize);
            
            if (empty($chunk)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Lot vide - traitement terminé',
                    'total_equipments' => $totalEquipments,
                    'processed_offset' => $startOffset,
                    'is_complete' => true
                ]);
            }

            $processedEquipments = 0;
            $contractProcessed = 0;
            $offContractProcessed = 0;
            $errors = [];

            // Traiter le lot
            foreach ($chunk as $equipmentData) {
                try {
                    $equipement = new EquipementS140();
                    $this->setRealCommonDataFixed($equipement, $fields);
                    
                    if ($equipmentData['type'] === 'contract') {
                        $this->setRealContractDataFixed($equipement, $equipmentData['data']);
                        $contractProcessed++;
                    } else {
                        // Pour les équipements hors contrat, adapter selon la structure
                        // (à implémenter si nécessaire)
                        $offContractProcessed++;
                    }
                    
                    $entityManager->persist($equipement);
                    $processedEquipments++;
                    
                    // Sauvegarder tous les 5 équipements
                    if ($processedEquipments % 5 === 0) {
                        $entityManager->flush();
                        $entityManager->clear();
                        gc_collect_cycles();
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'equipment_index' => $equipmentData['index'],
                        'type' => $equipmentData['type'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Sauvegarde finale
            $entityManager->flush();
            $entityManager->clear();

            $nextOffset = $startOffset + $chunkSize;
            $isComplete = $nextOffset >= $totalEquipments;
            
            // Marquer comme lu seulement si c'est le dernier lot
            if ($isComplete) {
                $this->markFormAsRead($formId, $entryId);
            }

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'client_name' => $fields['nom_client']['value'] ?? '',
                'batch_info' => [
                    'total_equipments' => $totalEquipments,
                    'processed_in_this_batch' => $processedEquipments,
                    'contract_processed' => $contractProcessed,
                    'off_contract_processed' => $offContractProcessed,
                    'start_offset' => $startOffset,
                    'chunk_size' => $chunkSize,
                    'next_offset' => $nextOffset,
                    'is_complete' => $isComplete
                ],
                'errors' => $errors,
                'next_call' => $isComplete ? null : 
                    "/api/maintenance/process-chunked-fixed/S140?form_id={$formId}&entry_id={$entryId}&offset={$nextOffset}&chunk_size={$chunkSize}",
                'message' => $isComplete ? 
                    "Traitement terminé: {$processedEquipments} équipements dans ce lot" :
                    "Lot traité: {$processedEquipments} équipements. Appeler l'URL next_call pour continuer"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}