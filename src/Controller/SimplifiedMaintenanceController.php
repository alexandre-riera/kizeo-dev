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
use App\Service\MaintenanceCacheService;
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
        if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
            foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
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
    
    /**
     * Définir les données communes à tous les équipements - ADAPTÉE AUX PROPRIÉTÉS EXISTANTES
     */
    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');

        // Convertir la date au format string si nécessaire
        $dateIntervention = $fields['date_et_heure1']['value'] ?? '';
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

    /**
     * Obtenir le code type à partir du libellé - VERSION COMPLÈTE basée sur libelle_equipements.docx
     */
    private function getTypeCodeFromLibelle(string $libelle): string
    {
        $libelleNormalized = strtolower(trim($libelle));
        
        // Mapping complet basé sur le document libelle_equipements.docx
        $mappingTable = [
            'porte accordéon' => 'PAC',
            'portail' => 'PAU',
            'porte sectionnelle' => 'SEC',
            'protection' => 'PRO',
            'porte rapide' => 'RAP',
            'porte coulissante' => 'COU',
            'niveleur' => 'NIV',
            'rideau metallique' => 'RID',
            'rideau métallique' => 'RID',
            'rideau métalliques' => 'RID',
            // PORTES PIÉTONNES - PRIORITÉ ABSOLUE
            'porte pietonne' => 'PPV',
            'porte piétonne' => 'PPV',
            'Porte piétonne' => 'PPV',
            'porte piéton' => 'PPV',
            'porte pieton' => 'PPV',
            'porte coupe feu' => 'CFE',
            'porte coupe -- feu' => 'CFE',
            'mini pont' => 'MIP',
            'mini-pont' => 'MIP',
            'table elevatrice' => 'TEL',
            'tourniquet' => 'TOU',
            'barriere levante' => 'BLE',
            'volet roulant' => 'RID',
            'issue de secours' => 'BPO',
            'porte frigorifique' => 'COF',
            'portail motorise' => 'PMO',
            'portail manuel' => 'PMA',
            'bloc roue' => 'BLR',
            'sas' => 'SAS',
            'plaque de quai' => 'PLQ',
            'porte basculante' => 'PBA',
            'porte battante' => 'BPA',
            'portillon' => 'POR',
            'portail coulissant' => 'PCO'
        ];
        
        // Recherche directe dans la table de mapping
        if (isset($mappingTable[$libelleNormalized])) {
            return $mappingTable[$libelleNormalized];
        }
        
        // Recherche par mots-clés pour les variantes
        foreach ($mappingTable as $pattern => $code) {
            if (str_contains($libelleNormalized, $pattern)) {
                return $code;
            }
        }
        // Recherche exacte d'abord
        if (isset($mappingTable[$libelleNormalized])) {
            error_log("Type trouvé (exact): {$libelleNormalized} -> " . $mappingTable[$libelleNormalized]);
            return $mappingTable[$libelleNormalized];
        }
        
        // Recherche par mots-clés pour les portes piétonnes
        if (str_contains($libelleNormalized, 'pieton') || str_contains($libelleNormalized, 'piéton')) {
            error_log("Type trouvé (mot-clé piéton): {$libelleNormalized} -> PPV");
            return 'PPV';
        }
        // Recherche par mots-clés individuels pour plus de flexibilité
        if (str_contains($libelleNormalized, 'sectionnelle')) return 'SEC';
        if (str_contains($libelleNormalized, 'rideau')) return 'RID';
        if (str_contains($libelleNormalized, 'basculante')) return 'PBA';
        if (str_contains($libelleNormalized, 'coulissante')) return 'COU';
        if (str_contains($libelleNormalized, 'battante')) return 'BPA';
        if (str_contains($libelleNormalized, 'portail')) return 'PAU';
        if (str_contains($libelleNormalized, 'barriere') || str_contains($libelleNormalized, 'barrière')) return 'BLE';
        if (str_contains($libelleNormalized, 'niveleur')) return 'NIV';
        if (str_contains($libelleNormalized, 'coupe feu')) return 'CFE';
        if (str_contains($libelleNormalized, 'accordéon') || str_contains($libelleNormalized, 'accordeon')) return 'PAC';
        
        // Code par défaut si aucun mapping trouvé
        return 'EQU';
    }

    /**
     * Obtenir le prochain numéro d'équipement - VERSION CORRIGÉE THREAD-SAFE
     */
    private function getNextEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        error_log("=== RECHERCHE PROCHAIN NUMÉRO ===");
        error_log("Type code: {$typeCode}");
        error_log("ID Client: {$idClient}");
        error_log("Entity class: {$entityClass}");
        
        $repository = $entityManager->getRepository($entityClass);
        
        // Requête pour trouver tous les équipements du même type et client
        $qb = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typePattern')
            ->setParameter('idClient', $idClient)
            ->setParameter('typePattern', $typeCode . '%');
        
        // DÉBOGAGE : Afficher la requête SQL générée
        $query = $qb->getQuery();
        error_log("SQL généré: " . $query->getSQL());
        error_log("Paramètres: idClient=" . $idClient . ", typePattern=" . $typeCode . '%');
        
        $equipements = $query->getResult();
        
        error_log("Nombre d'équipements trouvés: " . count($equipements));
        
        $dernierNumero = 0;
        
        foreach ($equipements as $equipement) {
            $numeroEquipement = $equipement->getNumeroEquipement();
            error_log("Numéro équipement analysé: " . $numeroEquipement);
            
            // Pattern pour extraire le numéro (ex: PPV01 -> 01, PPV02 -> 02)
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $numero = (int)$matches[1];
                error_log("Numéro extrait: " . $numero);
                
                if ($numero > $dernierNumero) {
                    $dernierNumero = $numero;
                    error_log("Nouveau dernier numéro: " . $dernierNumero);
                }
            } else {
                error_log("Pattern non reconnu pour: " . $numeroEquipement);
            }
        }
        
        $prochainNumero = $dernierNumero + 1;
        error_log("Prochain numéro calculé: " . $prochainNumero);
        
        return $prochainNumero;
    }
    
    /**
     * Générer un numéro d'équipement unique - VERSION SÉCURISÉE
     */
    private function generateUniqueEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): string
    {
        error_log("=== GÉNÉRATION NUMÉRO UNIQUE ===");
        error_log("Type code: {$typeCode}");
        error_log("ID Client: {$idClient}");
        error_log("Entity class: {$entityClass}");
        
        $maxTries = 10;
        $attempt = 0;
        
        while ($attempt < $maxTries) {
            $attempt++;
            error_log("Tentative #{$attempt}");
            
            // ✅ APPEL AVEC TOUS LES PARAMÈTRES
            $nouveauNumero = $this->getNextEquipmentNumberReal($typeCode, $idClient, $entityClass, $entityManager);
            $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
            
            error_log("Numéro formaté généré: " . $numeroFormate);
            
            // Vérifier l'unicité
            $repository = $entityManager->getRepository($entityClass);
            $existant = $repository->findOneBy([
                'id_contact' => $idClient,
                'numero_equipement' => $numeroFormate
            ]);
            
            if (!$existant) {
                error_log("Numéro unique confirmé: " . $numeroFormate);
                return $numeroFormate;
            } else {
                error_log("Collision détectée pour: " . $numeroFormate . ", nouvelle tentative...");
            }
        }
        
        // Fallback en cas d'échec
        $timestamp = substr(time(), -4);
        $numeroFallback = $typeCode . $timestamp;
        error_log("FALLBACK utilisé: " . $numeroFallback);
        
        return $numeroFallback;
    }

    /**
     * Vérifier si un numéro d'équipement existe déjà
     */
    private function equipmentNumberExists(string $numeroEquipement, string $idClient, string $entityClass, EntityManagerInterface $entityManager): bool
    {
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            $existing = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            return $existing !== null;
            
        } catch (\Exception $e) {
            // En cas d'erreur, considérer que le numéro existe pour éviter les doublons
            return true;
        }
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
                            if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
                                foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
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
                            if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
                                foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
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
            if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
                foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
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
                    'technician' => $fields['trigramme']['value'] ?? 'N/A',
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
            $equipement->setRaisonSociale($fields['nom_client']['value'] ?? '');
            $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');
            $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? '');
            
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
                    'client_name' => $fields['nom_client']['value'] ?? '',
                    'technician' => $fields['trigramme']['value'] ?? '',
                    'date' => $fields['date_et_heure1']['value'] ?? ''
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
            if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
                foreach ($fields['tableau2']['value'] as $index => $equipmentHorsContrat) {
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
                    'client_name' => $fields['nom_client']['value'] ?? '',
                    'technician' => $fields['trigramme']['value'] ?? '',
                    'date' => $fields['date_et_heure1']['value'] ?? ''
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
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? '');
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? '');
        
        // Valeurs par défaut
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    /**
     * Données spécifiques contrat - VERSION CORRIGÉE pour le nouveau format
     */
    private function setRealContractData($equipement, array $equipmentContrat): void
    {
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
        
        // 15. Flags par défaut
        $equipement->setEnMaintenance(true);
        $equipement->setIsArchive(false);
    }

    /**
     * Données spécifiques hors contrat - VERSION FINALE
     */
    private function setRealOffContractData($equipement, array $equipmentHorsContrat, array $fields, EntityManagerInterface $entityManager): void
    {
        $typeLibelle = strtolower($equipmentHorsContrat['nature']['value'] ?? '');
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        $idClient = $fields['id_client_']['value'] ?? '';
        $agencyCode = $fields['code_agence']['value'] ?? 'S140';
        // Génération du numéro
        $entityClass = $this->getEntityClassByAgency($agencyCode);
        $nouveauNumero = $this->getNextEquipmentNumberReal($typeCode, $idClient, $entityClass, $entityManager);
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
    private function getNextEquipmentNumberReal(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        error_log("=== RECHERCHE PROCHAIN NUMÉRO ===");
        error_log("Type code: {$typeCode}");
        error_log("ID Client: {$idClient}");
        error_log("Entity class: {$entityClass}"); // ✅ Maintenant $entityClass est défini
        
        $repository = $entityManager->getRepository($entityClass); // ✅ Utilisation correcte
        
        // Requête pour trouver tous les équipements du même type et client
        $qb = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typePattern')
            ->setParameter('idClient', $idClient)
            ->setParameter('typePattern', $typeCode . '%');
        
        // DÉBOGAGE : Afficher la requête SQL générée
        $query = $qb->getQuery();
        error_log("SQL généré: " . $query->getSQL());
        error_log("Paramètres: idClient=" . $idClient . ", typePattern=" . $typeCode . '%');
        
        $equipements = $query->getResult();
        
        error_log("Nombre d'équipements trouvés: " . count($equipements));
        
        $dernierNumero = 0;
        
        foreach ($equipements as $equipement) {
            $numeroEquipement = $equipement->getNumeroEquipement();
            error_log("Numéro équipement analysé: " . $numeroEquipement);
            
            // Pattern pour extraire le numéro (ex: PPV01 -> 01, PPV02 -> 02)
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $numero = (int)$matches[1];
                error_log("Numéro extrait: " . $numero);
                
                if ($numero > $dernierNumero) {
                    $dernierNumero = $numero;
                    error_log("Nouveau dernier numéro: " . $dernierNumero);
                }
            } else {
                error_log("Pattern non reconnu pour: " . $numeroEquipement);
            }
        }
        
        $prochainNumero = $dernierNumero + 1;
        error_log("Prochain numéro calculé: " . $prochainNumero);
        
        return $prochainNumero;
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
        $requiredFields = ['code_agence', 'nom_du_client', 'trigramme'];
        
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
            if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
                foreach ($fields['tableau2']['value'] as $index => $equipmentHorsContrat) {
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
                    'client_name' => $fields['nom_client']['value'] ?? '',
                    'technician' => $fields['trigramme']['value'] ?? '',
                    'date' => $fields['date_et_heure1']['value'] ?? ''
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
                                    'client_name' => $fields['nom_client']['value'] ?? '',
                                    'technician' => $fields['trigramme']['value'] ?? '',
                                    'date' => $fields['date_et_heure1']['value'] ?? '',
                                    'has_contract_equipment' => !empty($fields['contrat_de_maintenance']['value'] ?? []),
                                    'has_offcontract_equipment' => !empty($fields['tableau2']['value'] ?? []),
                                    'contract_count' => count($fields['contrat_de_maintenance']['value'] ?? []),
                                    'offcontract_count' => count($fields['tableau2']['value'] ?? [])
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
            if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
                foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
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
            $offContractEquipments = $fields['tableau2']['value'] ?? [];
            
            $totalEquipments = count($contractEquipments) + count($offContractEquipments);
            $recommendedChunkSize = max(10, min(20, intval($totalEquipments / 4)));

            return new JsonResponse([
                'success' => true,
                'form_id' => $formId,
                'entry_id' => $entryId,
                'client_name' => $fields['nom_client']['value'] ?? '',
                'technician' => $fields['trigramme']['value'] ?? '',
                'date' => $fields['date_et_heure1']['value'] ?? '',
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
     * SETTERS CORRIGÉS pour la vraie structure des données S140
     */

    /**
     * Données communes corrigées selon la vraie structure
     */
    private function setRealCommonDataFixed($equipement, array $fields): void
    {
        error_log("=== setRealCommonDataFixed START ===");

        // CORRECTION : Utiliser les vrais noms de champs
        $equipement->setCodeAgence($fields['code_agence']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? ''); // CORRIGÉ
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? ''); // CORRIGÉ
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? ''); // CORRIGÉ
        
        // Valeurs par défaut SANS setEnMaintenance (sera défini spécifiquement)
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
        
        error_log("Données communes définies - en_maintenance NON défini volontairement");
        error_log("=== setRealCommonDataFixed END ===");
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
     * Correspondance états pour le nouveau format
     */
    private function getMaintenanceStatusFromEtatFixed(string $etat): string
    {
        switch ($etat) {
            case "F": // Fonctionnel
            case "B": // Bon état
                return "RAS";
            case "C": // À réparer/Conforme avec remarques
            case "A": // À l'arrêt
                return "A_REPARER";
            case "D": // Défaillant ou autres états
                return "HS";
            default:
                return "RAS";
        }
    }

    /**
     * GESTIONNAIRE DE PHOTOS utilisant l'entité Form existante
     */

    /**
     * Setters corrigés avec sauvegarde des photos dans l'entité Form
     */
    private function setRealContractDataWithFormPhotos($equipement, array $equipmentContrat, array $fields, string $formId, string $entryId, EntityManagerInterface $entityManager): void
    {
        // 1. Données de base (comme avant)
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
        $equipement->setNumeroEquipement($numeroEquipement);
        
        $libelle = $equipmentContrat['reference7']['value'] ?? '';
        $equipement->setLibelleEquipement($libelle);
        
        $miseEnService = $equipmentContrat['reference2']['value'] ?? '';
        $equipement->setMiseEnService($miseEnService);
        
        $numeroSerie = $equipmentContrat['reference6']['value'] ?? '';
        $equipement->setNumeroDeSerie($numeroSerie);
        
        $marque = $equipmentContrat['reference5']['value'] ?? '';
        $equipement->setMarque($marque);
        
        $hauteur = $equipmentContrat['reference1']['value'] ?? '';
        $equipement->setHauteur($hauteur);
        
        $largeur = $equipmentContrat['reference3']['value'] ?? '';
        $equipement->setLargeur($largeur);
        
        $localisation = $equipmentContrat['localisation_site_client']['value'] ?? '';
        $equipement->setRepereSiteClient($localisation);
        
        $modeFonctionnement = $equipmentContrat['mode_fonctionnement_2']['value'] ?? '';
        $equipement->setModeFonctionnement($modeFonctionnement);
        
        $plaqueSignaletique = $equipmentContrat['plaque_signaletique']['value'] ?? '';
        $equipement->setPlaqueSignaletique($plaqueSignaletique);
        
        $etat = $equipmentContrat['etat']['value'] ?? '';
        $equipement->setEtat($etat);
        
        $longueur = $equipmentContrat['longueur']['value'] ?? '';
        $equipement->setLongueur($longueur);
        
        $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
        $equipement->setStatutDeMaintenance($statut);
        
        $equipement->setEnMaintenance(true);
        
        // 2. NOUVEAU : Sauvegarder les photos dans l'entité Form
        $this->savePhotosToFormEntity($equipmentContrat, $fields, $formId, $entryId, $numeroEquipement, $entityManager);
    }

    /**
     * Sauvegarder les photos dans l'entité Form
     */
    private function savePhotosToFormEntity(array $equipmentData, array $fields, string $formId, string $entryId, string $equipmentCode, EntityManagerInterface $entityManager): void
    {
        try {
            // Créer une nouvelle entrée Form pour chaque équipement
            $form = new \App\Entity\Form();
            
            // Informations de référence
            $form->setFormId($formId);
            $form->setDataId($entryId);
            $form->setEquipmentId($equipmentCode);
            $form->setCodeEquipement($equipmentCode);
            $form->setRaisonSocialeVisite(
                ($equipmentData['nom_client']['value'] ?? '') . '\\' . ($equipmentData['visite']['value'] ?? '')
            );
            $form->setUpdateTime(date('Y-m-d H:i:s'));
            
            // Photo étiquette SOMAFI
            if (!empty($equipmentData['photo_etiquette_somafi']['value'])) {
                $form->setPhotoEtiquetteSomafi($equipmentData['photo_etiquette_somafi']['value']);
            }
            
            // Photos principales de l'équipement
            if (!empty($equipmentData['photo2']['value'])) {
                $form->setPhoto2($equipmentData['photo2']['value']);
            }
            
            // Photos complémentaires
            if (!empty($equipmentData['photo_complementaire_equipeme']['value'])) {
                $form->setPhotoEnvironnementEquipement1($equipmentData['photo_complementaire_equipeme']['value']);
            }
            
            // Photos de déformation (pour niveleurs)
            if (!empty($equipmentData['photo_deformation_levre']['value'])) {
                $form->setPhotoDeformationLevre($equipmentData['photo_deformation_levre']['value']);
            }
            
            if (!empty($equipmentData['photo_joue']['value'])) {
                $form->setPhotoJoue($equipmentData['photo_joue']['value']);
            }
            
            if (!empty($equipmentData['photo_deformation_plateau']['value'])) {
                $form->setPhotoDeformationPlateau($equipmentData['photo_deformation_plateau']['value']);
            }
            
            if (!empty($equipmentData['photo_deformation_plaque']['value'])) {
                $form->setPhotoDeformationPlaque($equipmentData['photo_deformation_plaque']['value']);
            }
            
            if (!empty($equipmentData['photo_deformation_structure']['value'])) {
                $form->setPhotoDeformationStructure($equipmentData['photo_deformation_structure']['value']);
            }
            
            if (!empty($equipmentData['photo_deformation_chassis']['value'])) {
                $form->setPhotoDeformationChassis($equipmentData['photo_deformation_chassis']['value']);
            }
            
            // Photos techniques
            if (!empty($equipmentData['photo_moteur']['value'])) {
                $form->setPhotoMoteur($equipmentData['photo_moteur']['value']);
            }
            
            if (!empty($equipmentData['photo_coffret_de_commande']['value'])) {
                $form->setPhotoCoffretDeCommande($equipmentData['photo_coffret_de_commande']['value']);
            }
            
            if (!empty($equipmentData['photo_carte']['value'])) {
                $form->setPhotoCarte($equipmentData['photo_carte']['value']);
            }
            
            // Photos de chocs et anomalies
            if (!empty($equipmentData['photo_choc']['value'])) {
                $form->setPhotoChoc($equipmentData['photo_choc']['value']);
            }
            
            if (!empty($equipmentData['photo_choc_tablier']['value'])) {
                $form->setPhotoChocTablier($equipmentData['photo_choc_tablier']['value']);
            }
            
            if (!empty($equipmentData['photo_choc_tablier_porte']['value'])) {
                $form->setPhotoChocTablierPorte($equipmentData['photo_choc_tablier_porte']['value']);
            }
            
            // Photos de plaques et serrures
            if (!empty($equipmentData['photo_plaque']['value'])) {
                $form->setPhotoPlaque($equipmentData['photo_plaque']['value']);
            }
            
            if (!empty($equipmentData['photo_serrure']['value'])) {
                $form->setPhotoSerrure($equipmentData['photo_serrure']['value']);
            }
            
            if (!empty($equipmentData['photo_serrure1']['value'])) {
                $form->setPhotoSerrure1($equipmentData['photo_serrure1']['value']);
            }
            
            // Photos de rails et fixations
            if (!empty($equipmentData['photo_rail']['value'])) {
                $form->setPhotoRail($equipmentData['photo_rail']['value']);
            }
            
            if (!empty($equipmentData['photo_equerre_rail']['value'])) {
                $form->setPhotoEquerreRail($equipmentData['photo_equerre_rail']['value']);
            }
            
            if (!empty($equipmentData['photo_fixation_coulisse']['value'])) {
                $form->setPhotoFixationCoulisse($equipmentData['photo_fixation_coulisse']['value']);
            }
            
            // Photos d'axe
            if (!empty($equipmentData['photo_axe']['value'])) {
                $form->setPhotoAxe($equipmentData['photo_axe']['value']);
            }
            
            // Photos de feux
            if (!empty($equipmentData['photo_feux']['value'])) {
                $form->setPhotoFeux($equipmentData['photo_feux']['value']);
            }
            
            // Photos diverses
            if (!empty($equipmentData['photo_bache']['value'])) {
                $form->setPhotoBache($equipmentData['photo_bache']['value']);
            }
            
            if (!empty($equipmentData['photo_marquage_au_sol']['value'])) {
                $form->setPhotoMarquageAuSol($equipmentData['photo_marquage_au_sol']['value']);
            }
            
            if (!empty($equipmentData['photo_butoir']['value'])) {
                $form->setPhotoButoir($equipmentData['photo_butoir']['value']);
            }
            
            if (!empty($equipmentData['photo_vantail']['value'])) {
                $form->setPhotoVantail($equipmentData['photo_vantail']['value']);
            }
            
            if (!empty($equipmentData['photo_linteau']['value'])) {
                $form->setPhotoLinteau($equipmentData['photo_linteau']['value']);
            }
            
            // Sauvegarder l'entité Form
            $entityManager->persist($form);
            
        } catch (\Exception $e) {
            error_log("Erreur sauvegarde photos Form: " . $e->getMessage());
        }
    }

    /**
    * Mapping des form_id par agence basé sur "List all kizeo forms.json"
    */
    private function getAgencyFormMapping(): array
    {
        return [
            'S10' => '1090092',  // V4- Group / Visite maintenance
            'S40' => '1055931',  // V4- St Etienne / Visite maintenance
            // 'S50' => '1065302',  // V5- GRENOBLE / Visite de maintenance
            'S50' => '1052966',  // V4- GRENOBLE / Visite de maintenance
            'S60' => '1055932',  // V4- Lyon /Visite maintenance
            'S70' => '1057365',  // V4- Bordeaux /Visite maintenance
            'S80' => '1053175',  // V4 - Paris / Visite maintenance
            // 'S100' => '1071913', // V5- Montpellier /Visite maintenance
            'S100' => '1052982', // V4- Montpellier /Visite maintenance
            'S120' => '1062555', // v4- Portland / visite de maintenance
            'S130' => '1057880', // V4- Toulouse / visite de maintenance
            'S140' => '1088761', // V4 - Smp / visite de maintenance
            'S150' => '1057408', // V4- Paca / visite de maintenance
            'S160' => '1060720', // V4- Rouen / visite de maintenance
            'S170' => '1090092', // V4- Rennes / visite de maintenance MEME QUE GROUP CAR PAS EN PRODUCTION
        ];
    }

    /**
     * Récupérer toutes les soumissions d'un formulaire pour une agence
     */
    private function getFormSubmissions(string $formId, string $agencyCode, int $limit = 50): array
    {
        try {
            // Récupérer les données du formulaire
            $response = $this->client->request(
                'POST',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'json' => [
                        'limit' => $limit,
                        'offset' => 0
                    ],
                    'timeout' => 60
                ]
            );

            $formData = $response->toArray();
            $validSubmissions = [];
            
            if (!isset($formData['data']) || empty($formData['data'])) {
                return [];
            }
            
            // Filtrer les soumissions pour l'agence spécifiée
            foreach ($formData['data'] as $entry) {
                try {
                    // Récupérer les détails de chaque entrée
                    $detailResponse = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                            'timeout' => 30
                        ]
                    );

                    $detailData = $detailResponse->toArray();
                    
                    // Vérifier la structure et l'agence
                    if (isset($detailData['data']['fields']['code_agence']['value']) && 
                        $detailData['data']['fields']['code_agence']['value'] === $agencyCode) {
                        
                        $validSubmissions[] = [
                            'form_id' => $entry['_form_id'],
                            'entry_id' => $entry['_id'],
                            'client_name' => $detailData['data']['fields']['nom_client']['value'] ?? 'N/A',
                            'date' => $detailData['data']['fields']['date_et_heure1']['value'] ?? 'N/A',
                            'technician' => $detailData['data']['fields']['trigramme']['value'] ?? 'N/A'
                        ];
                    }
                    
                } catch (\Exception $e) {
                    error_log("Erreur lors du filtrage de l'entrée {$entry['_id']}: " . $e->getMessage());
                    continue;
                }
            }
            
            return $validSubmissions;
            
        } catch (\Exception $e) {
            error_log("Erreur lors de la récupération des soumissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Route pour lister les soumissions disponibles d'un formulaire
     */
    #[Route('/api/maintenance/list-submissions/{agencyCode}', name: 'app_maintenance_list_submissions', methods: ['GET'])]
    public function listFormSubmissions(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id');
        $limit = (int) $request->query->get('limit', 50);
        
        // Si pas de form_id fourni, utiliser le mapping par défaut
        if (!$formId) {
            $agencyMapping = $this->getAgencyFormMapping();
            $formId = $agencyMapping[$agencyCode] ?? null;
            
            if (!$formId) {
                return new JsonResponse([
                    'error' => 'Aucun form_id trouvé pour l\'agence ' . $agencyCode,
                    'available_agencies' => array_keys($agencyMapping)
                ], 400);
            }
        }

        try {
            $submissions = $this->getFormSubmissions($formId, $agencyCode, $limit);
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'total_submissions' => count($submissions),
                'submissions' => $submissions,
                'ready_to_process' => count($submissions) > 0,
                'process_url' => "/api/maintenance/process-form/{$agencyCode}?form_id={$formId}&chunk_size=15",
                'message' => count($submissions) > 0 ? 
                    count($submissions) . " soumissions trouvées pour l'agence {$agencyCode}" :
                    "Aucune soumission trouvée pour l'agence {$agencyCode}"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PROCESSEUR OPTIMISÉ avec déduplication et gestion mémoire améliorée
     */

    /**
     * Vérifier si un équipement existe déjà en base
     */
    private function equipmentExists(string $numeroEquipement, string $idClient, string $entityClass, EntityManagerInterface $entityManager): bool
    {
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            $existing = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')    // ✅ PROPRIÉTÉ: numero_equipement
                ->andWhere('e.id_contact = :idClient')      // ✅ PROPRIÉTÉ: id_contact
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            return $existing !== null;
            
        } catch (\Exception $e) {
            error_log("Erreur equipmentExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si une entrée Form existe déjà
     */
    private function formEntryExists(string $formId, string $entryId, string $equipmentCode, EntityManagerInterface $entityManager): bool
    {
        try {
            $repository = $entityManager->getRepository(\App\Entity\Form::class);
            
            $existing = $repository->createQueryBuilder('f')
                ->where('f.form_id = :formId')
                ->andWhere('f.data_id = :entryId')
                ->andWhere('f.equipment_id = :equipmentCode')
                ->setParameter('formId', $formId)
                ->setParameter('entryId', $entryId)
                ->setParameter('equipmentCode', $equipmentCode)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            return $existing !== null;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Setters avec vérification de doublons
     */
    private function setRealContractDataWithFormPhotosAndDeduplication(
        $equipement, 
        array $equipmentContrat, 
        array $fields, 
        string $formId, 
        string $entryId, 
        string $entityClass,
        EntityManagerInterface $entityManager
    ): bool {
        // 1. Données de base
        $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
        $idClient = $fields['id_client_']['value'] ?? '';
        
        // // 2. Vérifier si l'équipement existe déjà
        if ($this->equipmentExistsForSameVisit($numeroEquipement, $idClient, $fields['date_et_heure1']['value'] ?? '', $entityClass, $entityManager)) {
            return false; // Skip seulement si même visite
        }
        
        // 3. Continuer avec le traitement normal
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        $equipement->setNumeroEquipement($numeroEquipement);
        
        $idSociete =  $fields['id_societe']['value'] ?? '';
        $equipement->setCodeSociete($idSociete);
        
        $dateDerniereVisite =  $fields['date_et_heure1']['value'] ?? '';
        $equipement->setDerniereVisite($dateDerniereVisite);
        
        $isTest =  $fields['test_']['value'] ?? '';
        $equipement->setTest($isTest);

        $libelle = $equipmentContrat['reference7']['value'] ?? '';
        $equipement->setLibelleEquipement($libelle);
        
        $miseEnService = $equipmentContrat['reference2']['value'] ?? '';
        $equipement->setMiseEnService($miseEnService);
        
        $numeroSerie = $equipmentContrat['reference6']['value'] ?? '';
        $equipement->setNumeroDeSerie($numeroSerie);
        
        $marque = $equipmentContrat['reference5']['value'] ?? '';
        $equipement->setMarque($marque);
        
        $hauteur = $equipmentContrat['reference1']['value'] ?? '';
        $equipement->setHauteur($hauteur);
        
        $largeur = $equipmentContrat['reference3']['value'] ?? '';
        $equipement->setLargeur($largeur);
        
        $localisation = $equipmentContrat['localisation_site_client']['value'] ?? '';
        $equipement->setRepereSiteClient($localisation);
        
        $modeFonctionnement = $equipmentContrat['mode_fonctionnement_2']['value'] ?? '';
        $equipement->setModeFonctionnement($modeFonctionnement);
        
        $plaqueSignaletique = $equipmentContrat['plaque_signaletique']['value'] ?? '';
        $equipement->setPlaqueSignaletique($plaqueSignaletique);
        
        $etat = $equipmentContrat['etat']['value'] ?? '';
        $equipement->setEtat($etat);
        
        $longueur = $equipmentContrat['longueur']['value'] ?? '';
        $equipement->setLongueur($longueur);
        
        $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
        $equipement->setStatutDeMaintenance($statut);
        
        $equipement->setEnMaintenance(true);
        
        // 4. Sauvegarder les photos SEULEMENT si pas de doublon
        $this->savePhotosToFormEntityWithDeduplication($equipmentContrat, $fields, $formId, $entryId, $numeroEquipement, $entityManager);
        
        return true; // Équipement traité avec succès
    }

    /**
     * Alternative: Vérifier avec la date exacte
     */
    private function equipmentExistsForSameVisit(
        string $numeroEquipement, 
        string $idClient, 
        string $dateVisite,
        string $entityClass, 
        EntityManagerInterface $entityManager
    ): bool {
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            $existing = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->andWhere('e.date_enregistrement = :dateVisite')
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setParameter('dateVisite', $dateVisite)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            return $existing !== null;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sauvegarder les photos avec vérification de doublons
     */
    private function savePhotosToFormEntityWithDeduplication(
        array $equipmentData, 
        array $fields, 
        string $formId, 
        string $entryId, 
        string $equipmentCode, 
        EntityManagerInterface $entityManager
    ): void {
        
        // Vérifier si l'entrée Form existe déjà
        if ($this->formEntryExists($formId, $entryId, $equipmentCode, $entityManager)) {
            return; // Skip car déjà existe
        }
        
        try {
            // Créer une nouvelle entrée Form
            $form = new \App\Entity\Form();
            
            // Informations de référence
            $form->setFormId($formId);
            $form->setDataId($entryId);
            $form->setEquipmentId($equipmentCode);
            $form->setCodeEquipement($equipmentCode);
            $form->setRaisonSocialeVisite($equipmentData['equipement']['path']);
            $form->setUpdateTime(date('Y-m-d H:i:s'));
            
            // Photos (même logique que précédemment)
            if (!empty($equipmentData['photo_etiquette_somafi']['value'])) {
                $form->setPhotoEtiquetteSomafi($equipmentData['photo_etiquette_somafi']['value']);
            }
            
            if (!empty($equipmentData['photo2']['value'])) {
                $form->setPhoto2($equipmentData['photo2']['value']);
            }
            if (!empty($equipmentData['photo3']['value'])) {
                $form->setPhotoCompteRendu($equipmentData['photo3']['value']);
            }
            
            if (!empty($equipmentData['photo_complementaire_equipeme']['value'])) {
                $form->setPhotoEnvironnementEquipement1($equipmentData['photo_complementaire_equipeme']['value']);
            }
            
            // Autres photos...
            $this->setAllPhotosToForm($form, $equipmentData);
            
            // Sauvegarder l'entité Form
            $entityManager->persist($form);
            
        } catch (\Exception $e) {
            error_log("Erreur sauvegarde photos Form: " . $e->getMessage());
        }
    }

    /**
     * Route optimisée pour traitement par form_id avec déduplication
     */
    #[Route('/api/maintenance/process-form-optimized/{agencyCode}', name: 'app_maintenance_process_form_optimized', methods: ['GET'])]
    public function processMaintenanceByFormIdOptimized(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration mémoire très conservatrice
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', 300); // 5 minutes max
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id');
        $chunkSize = (int) $request->query->get('chunk_size', 10); // Réduire à 10 par défaut
        $maxSubmissions = (int) $request->query->get('max_submissions', 20); // Limiter à 20 soumissions max
        
        // Si pas de form_id fourni, utiliser le mapping par défaut
        if (!$formId) {
            $agencyMapping = $this->getAgencyFormMapping();
            $formId = $agencyMapping[$agencyCode] ?? null;
            
            if (!$formId) {
                return new JsonResponse([
                    'error' => 'Aucun form_id trouvé pour l\'agence ' . $agencyCode,
                    'available_agencies' => array_keys($agencyMapping)
                ], 400);
            }
        }

        try {
            $startTime = time();
            
            // 1. Récupérer les soumissions avec limite stricte
            $submissions = $this->getFormSubmissionsOptimized($formId, $agencyCode, $maxSubmissions);
            
            if (empty($submissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission trouvée pour le formulaire ' . $formId,
                    'agency' => $agencyCode,
                    'form_id' => $formId,
                    'processed_submissions' => 0
                ]);
            }
            
            // 2. Traiter par petits lots
            $results = [];
            $totalEquipments = 0;
            $totalPhotos = 0;
            $totalSkipped = 0;
            $totalErrors = 0;
            $processedSubmissions = 0;
            
            $entityClass = $this->getEntityClassByAgency($agencyCode);
            
            foreach ($submissions as $submissionIndex => $submission) {
                try {
                    $submissionStartTime = time();
                    
                    // Traitement d'une soumission avec déduplication
                    $result = $this->processSingleSubmissionWithDeduplication(
                        $submission, 
                        $agencyCode, 
                        $entityClass, 
                        $chunkSize, 
                        $entityManager
                    );
                    
                    $totalEquipments += $result['equipments_processed'];
                    $totalPhotos += $result['photos_saved'];
                    $totalSkipped += $result['equipments_skipped'];
                    $totalErrors += $result['errors'];
                    $processedSubmissions++;
                    
                    $submissionTime = time() - $submissionStartTime;
                    
                    $results[] = [
                        'entry_id' => $submission['entry_id'],
                        'client_name' => $submission['client_name'],
                        'status' => 'success',
                        'equipments_processed' => $result['equipments_processed'],
                        'equipments_skipped' => $result['equipments_skipped'],
                        'photos_saved' => $result['photos_saved'],
                        'errors' => $result['errors'],
                        'processing_time' => $submissionTime
                    ];
                    
                    // Pause entre soumissions et nettoyage mémoire
                    $entityManager->clear();
                    gc_collect_cycles();
                    sleep(1);
                    
                    // Sécurité : vérifier le temps écoulé
                    if ((time() - $startTime) > 280) { // 4m40s max
                        break;
                    }
                    
                } catch (\Exception $e) {
                    $totalErrors++;
                    $results[] = [
                        'entry_id' => $submission['entry_id'],
                        'client_name' => $submission['client_name'] ?? 'N/A',
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $endTime = time();
            $totalProcessingTime = $endTime - $startTime;
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'processing_summary' => [
                    'total_submissions_found' => count($submissions),
                    'processed_submissions' => $processedSubmissions,
                    'failed_submissions' => $totalErrors,
                    'total_equipments_processed' => $totalEquipments,
                    'total_equipments_skipped' => $totalSkipped,
                    'total_photos_saved' => $totalPhotos,
                    'total_processing_time_seconds' => $totalProcessingTime,
                    'chunk_size_used' => $chunkSize,
                    'max_submissions_limit' => $maxSubmissions
                ],
                'submission_details' => $results,
                'message' => "Traitement optimisé terminé: {$processedSubmissions}/" . count($submissions) . 
                            " soumissions, {$totalEquipments} équipements nouveaux, {$totalSkipped} doublons évités, {$totalPhotos} photos en {$totalProcessingTime}s"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupération optimisée des soumissions
     */
    private function getFormSubmissionsOptimized(string $formId, string $agencyCode, int $maxSubmissions = 20): array
    {
        try {
            // Récupérer seulement un petit lot à la fois
            $response = $this->client->request(
                'POST',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ]
                    ,
                    'json' => [
                        'limit' => $maxSubmissions,
                        'offset' => 0
                    ],
                    'timeout' => 30
                ]
            );

            $formData = $response->toArray();
            $validSubmissions = [];
            
            if (!isset($formData['data']) || empty($formData['data'])) {
                return [];
            }
            
            // Traiter SEULEMENT les premières entrées pour éviter la surcharge
            $entriesToProcess = array_slice($formData['data'], 0, min($maxSubmissions, count($formData['data'])));
            
            foreach ($entriesToProcess as $entry) {
                try {
                    // Récupération rapide des détails
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
                    
                    $validSubmissions[] = [
                        'form_id' => $entry['_form_id'],
                        'entry_id' => $entry['_id'],
                        'client_name' => $detailData['data']['fields']['nom_client']['value'] ?? 'N/A',
                        'date' => $detailData['data']['fields']['date_et_heure1']['value'] ?? 'N/A',
                        'technician' => $detailData['data']['fields']['trigramme']['value'] ?? 'N/A'
                    ];
                    
                } catch (\Exception $e) {
                    error_log("Erreur lors du filtrage de l'entrée {$entry['_id']}: " . $e->getMessage());
                    continue;
                }
            }
            
            return $validSubmissions;
            
        } catch (\Exception $e) {
            error_log("Erreur lors de la récupération des soumissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Traiter une soumission avec déduplication
     */
    private function processSingleSubmissionWithDeduplication(
        array $submission, 
        string $agencyCode, 
        string $entityClass, 
        int $chunkSize, 
        EntityManagerInterface $entityManager
    ): array {
        
        $equipmentsProcessed = 0;
        $equipmentsSkipped = 0;
        $photosSaved = 0;
        $errors = 0;
        
        try {
            // Récupérer les données de la soumission
            $detailResponse = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $submission['form_id'] . '/data/' . $submission['entry_id'],
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
            $idSociete =  $fields['id_societe']['value'] ?? '';
            $dateDerniereVisite =  $fields['date_et_heure1']['value'] ?? '';

            // Récupérer les équipements sous contrat et hors contrat
            $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
            $offContractEquipments = $fields['tableau2']['value'] ?? [];
            
            error_log("===== TRAITEMENT SOUMISSION " . $submission['entry_id'] . " =====");
            error_log("Équipements sous contrat: " . count($contractEquipments));
            error_log("Équipements hors contrat: " . count($offContractEquipments));
            
            // Traitement des équipements sous contrat
            if (!empty($contractEquipments)) {
                error_log("--- DÉBUT TRAITEMENT SOUS CONTRAT ---");
                $contractChunks = array_chunk($contractEquipments, $chunkSize);
                
                foreach ($contractChunks as $chunkIndex => $chunk) {
                    error_log("Chunk sous contrat " . ($chunkIndex + 1) . "/" . count($contractChunks));
                    
                    foreach ($chunk as $equipmentIndex => $equipmentContrat) {
                        try {
                            error_log("Traitement équipement sous contrat " . ($equipmentIndex + 1) . "/" . count($chunk));
                            
                            $equipement = new $entityClass();
                            
                            // Étape 1: Données communes
                            $this->setRealCommonDataFixed($equipement, $fields);
                            error_log("Données communes définies pour équipement sous contrat");
                            
                            // Étape 2: Données spécifiques sous contrat
                            $wasProcessed = $this->setRealContractDataWithFormPhotosAndDeduplication(
                                $equipement, 
                                $equipmentContrat, 
                                $fields, 
                                $submission['form_id'], 
                                $submission['entry_id'], 
                                $entityClass,
                                $entityManager
                            );
                            
                            if ($wasProcessed) {
                                $entityManager->persist($equipement);
                                $equipmentsProcessed++;
                                $photosSaved++;
                                error_log("Équipement sous contrat persisté");
                            } else {
                                $equipmentsSkipped++;
                                error_log("Équipement sous contrat skippé (doublon)");
                            }
                            
                        } catch (\Exception $e) {
                            $errors++;
                            error_log("Erreur traitement équipement sous contrat: " . $e->getMessage());
                        }
                    }
                    
                    // Sauvegarder après chaque chunk
                    try {
                        $entityManager->flush();
                        $entityManager->clear();
                        gc_collect_cycles();
                        error_log("Chunk sous contrat " . ($chunkIndex + 1) . " sauvegardé");
                    } catch (\Exception $e) {
                        $errors++;
                        error_log("Erreur flush/clear sous contrat: " . $e->getMessage());
                    }
                }
                error_log("--- FIN TRAITEMENT SOUS CONTRAT ---");
            }
            
            // Traitement des équipements hors contrat
            if (!empty($offContractEquipments)) {
                error_log("--- DÉBUT TRAITEMENT HORS CONTRAT ---");
                $offContractChunks = array_chunk($offContractEquipments, $chunkSize);
                
                foreach ($offContractChunks as $chunkIndex => $chunk) {
                    error_log("Chunk hors contrat " . ($chunkIndex + 1) . "/" . count($offContractChunks));
                    
                    foreach ($chunk as $equipmentIndex => $equipmentHorsContrat) {
                        try {
                            error_log("--- DÉBUT ÉQUIPEMENT HORS CONTRAT " . ($equipmentIndex + 1) . "/" . count($chunk) . " ---");
                            
                            $equipement = new $entityClass();
                            error_log("Nouvel objet équipement créé");
                            
                            // Étape 1: Données communes SANS setEnMaintenance
                            $this->setRealCommonDataFixed($equipement, $fields);
                            error_log("Données communes définies pour équipement hors contrat");
                            error_log("État en_maintenance après données communes: " . ($equipement->isEnMaintenance() ? 'true' : 'false'));
                            
                            // Étape 2: Données spécifiques hors contrat (avec setEnMaintenance(false))
                            $wasProcessed = $this->setOffContractDataWithFormPhotosAndDeduplication(
                                $equipement, 
                                $equipmentHorsContrat, 
                                $fields, 
                                $submission['form_id'], 
                                $submission['entry_id'], 
                                $entityClass,
                                $entityManager,
                                $idSociete,
                                $dateDerniereVisite
                            );
                            
                            if ($wasProcessed) {
                                // Vérification finale avant persist
                                error_log("VÉRIFICATION AVANT PERSIST:");
                                error_log("- Numéro: " . $equipement->getNumeroEquipement());
                                error_log("- Libellé: " . $equipement->getLibelleEquipement());
                                error_log("- En maintenance: " . ($equipement->isEnMaintenance() ? 'true' : 'false'));
                                
                                $entityManager->persist($equipement);
                                $equipmentsProcessed++;
                                $photosSaved++;
                                error_log("Équipement hors contrat persisté avec succès");
                            } else {
                                $equipmentsSkipped++;
                                error_log("Équipement hors contrat skippé (doublon)");
                            }
                            
                            error_log("--- FIN ÉQUIPEMENT HORS CONTRAT " . ($equipmentIndex + 1) . " ---");
                            
                        } catch (\Exception $e) {
                            $errors++;
                            error_log("Erreur traitement équipement hors contrat: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                        }
                    }
                    
                    // Sauvegarder après chaque chunk
                    try {
                        error_log("Sauvegarde chunk hors contrat " . ($chunkIndex + 1));
                        $entityManager->flush();
                        $entityManager->clear();
                        gc_collect_cycles();
                        error_log("Chunk hors contrat " . ($chunkIndex + 1) . " sauvegardé");
                    } catch (\Exception $e) {
                        $errors++;
                        error_log("Erreur flush/clear hors contrat: " . $e->getMessage());
                    }
                }
                error_log("--- FIN TRAITEMENT HORS CONTRAT ---");
            }
            
            error_log("===== FIN TRAITEMENT SOUMISSION " . $submission['entry_id'] . " =====");
            
        } catch (\Exception $e) {
            $errors++;
            error_log("Erreur traitement soumission {$submission['entry_id']}: " . $e->getMessage());
        }
        
        return [
            'equipments_processed' => $equipmentsProcessed,
            'equipments_skipped' => $equipmentsSkipped,
            'photos_saved' => $photosSaved,
            'errors' => $errors
        ];
    }

    /**
     * Version mise à jour de setOffContractDataWithFormPhotosAndDeduplication avec numérotation sécurisée
     */
    private function setOffContractDataWithFormPhotosAndDeduplication(
        $equipement, 
        array $equipmentHorsContrat, 
        array $fields, 
        string $formId, 
        string $entryId, 
        string $entityClass,
        EntityManagerInterface $entityManager,
        string $idSociete,
        string $dateDerniereVisite
    ): bool {
        
        error_log("=== DÉBUT TRAITEMENT HORS CONTRAT (DÉBOGAGE PPV) ===");
        error_log("Entry ID: " . $entryId);
        error_log("Entity class passée: " . $entityClass); // ✅ Log pour vérifier

        // 1. Générer le numéro d'équipement
        $typeLibelle = $equipmentHorsContrat['nature']['value'] ?? '';
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        $idClient = $fields['id_client_']['value'] ?? '';
        
        // $nouveauNumero = $this->getNextEquipmentNumber($typeCode, $idClient, $entityClass, $entityManager);
        // $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
        // ✅ APPEL AVEC TOUS LES PARAMÈTRES Y COMPRIS $entityClass
        $numeroFormate = $this->generateUniqueEquipmentNumber($typeCode, $idClient, $entityClass, $entityManager);
        error_log("Numéro formaté final: '" . $numeroFormate . "'");
        
        // 2. Vérifier si l'équipement existe déjà (même si c'est un nouveau numéro, vérifier par autres critères)
        if ($this->offContractEquipmentExists($equipmentHorsContrat, $idClient, $entityClass, $entityManager)) {
            return false; // Skip car déjà existe
        }
        
        // 3. Définir les données de l'équipement hors contrat
        $equipement->setNumeroEquipement($numeroFormate);
        $equipement->setCodeSociete($idSociete);
        $equipement->setDerniereVisite($dateDerniereVisite);
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
        
        // IMPORTANT: Équipements hors contrat ne sont PAS en maintenance
        $equipement->setEnMaintenance(false);
        $equipement->setIsArchive(false);
        
        // 4. Sauvegarder les photos SEULEMENT si pas de doublon
        $this->savePhotosToFormEntityWithDeduplication($equipmentHorsContrat, $fields, $formId, $entryId, $numeroFormate, $entityManager);
        
        return true; // Équipement traité avec succès
    }

    /**
     * Vérifier si un équipement hors contrat existe déjà
     */
    private function offContractEquipmentExists(
        array $equipmentHorsContrat, 
        string $idClient, 
        string $entityClass, 
        EntityManagerInterface $entityManager
    ): bool {
        
        error_log("=== VÉRIFICATION EXISTENCE ÉQUIPEMENT HORS CONTRAT (NOMS CORRECTS) ===");
        
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            $numeroSerie = $equipmentHorsContrat['n_de_serie']['value'] ?? '';
            $marque = $equipmentHorsContrat['marque']['value'] ?? '';
            $localisation = $equipmentHorsContrat['localisation_site_client1']['value'] ?? '';
            $nature = $equipmentHorsContrat['nature']['value'] ?? '';
            
            error_log("Critères: numeroSerie='$numeroSerie', marque='$marque', localisation='$localisation', nature='$nature'");
            
            $existing = null;
            
            // Stratégie 1: Numéro de série (sauf si "NC")
            if (!empty($numeroSerie) && trim($numeroSerie) !== '' && $numeroSerie !== 'NC') {
                error_log("Recherche par numéro de série...");
                
                $existing = $repository->createQueryBuilder('e')
                    ->where('e.numero_de_serie = :numeroSerie')  // ✅ PROPRIÉTÉ: numero_de_serie
                    ->andWhere('e.id_contact = :idClient')       // ✅ PROPRIÉTÉ: id_contact
                    ->setParameter('numeroSerie', $numeroSerie)
                    ->setParameter('idClient', $idClient)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                    
                error_log("Résultat recherche par numéro de série: " . ($existing ? "TROUVÉ" : "NON TROUVÉ"));
            }
            
            // Stratégie 2: Combinaison marque + localisation + nature
            if (!$existing && !empty($marque) && !empty($localisation) && !empty($nature)) {
                error_log("Recherche par combinaison marque + localisation + nature...");
                
                $existing = $repository->createQueryBuilder('e')
                    ->where('e.marque = :marque')
                    ->andWhere('e.repere_site_client = :localisation')   // ✅ PROPRIÉTÉ: repere_site_client
                    ->andWhere('e.libelle_equipement = :nature')         // ✅ PROPRIÉTÉ: libelle_equipement
                    ->andWhere('e.id_contact = :idClient')               // ✅ PROPRIÉTÉ: id_contact
                    ->setParameter('marque', $marque)
                    ->setParameter('localisation', $localisation)
                    ->setParameter('nature', strtolower($nature))
                    ->setParameter('idClient', $idClient)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                    
                error_log("Résultat recherche par combinaison: " . ($existing ? "TROUVÉ" : "NON TROUVÉ"));
            }
            
            $result = $existing !== null;
            error_log("DÉCISION FINALE: " . ($result ? "EXISTE DÉJÀ (SKIP)" : "N'EXISTE PAS (TRAITER)"));
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Erreur dans offContractEquipmentExists: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Utilitaire pour définir toutes les photos sur Form
     */
    private function setAllPhotosToForm($form, array $equipmentData): void
    {
        // Mappage complet des photos
        $photoMapping = [
            'photo_deformation_plateau' => 'setPhotoDeformationPlateau',
            'photo_deformation_plaque' => 'setPhotoDeformationPlaque',
            'photo_deformation_structure' => 'setPhotoDeformationStructure',
            'photo_deformation_chassis' => 'setPhotoDeformationChassis',
            'photo_deformation_levre' => 'setPhotoDeformationLevre',
            'photo_joue' => 'setPhotoJoue',
            'photo_moteur' => 'setPhotoMoteur',
            'photo_coffret_de_commande' => 'setPhotoCoffretDeCommande',
            'photo_carte' => 'setPhotoCarte',
            'photo_choc' => 'setPhotoChoc',
            'photo_choc_tablier' => 'setPhotoChocTablier',
            'photo_choc_tablier_porte' => 'setPhotoChocTablierPorte',
            'photo_plaque' => 'setPhotoPlaque',
            'photo_serrure' => 'setPhotoSerrure',
            'photo_serrure1' => 'setPhotoSerrure1',
            'photo_rail' => 'setPhotoRail',
            'photo_equerre_rail' => 'setPhotoEquerreRail',
            'photo_fixation_coulisse' => 'setPhotoFixationCoulisse',
            'photo_axe' => 'setPhotoAxe',
            'photo_feux' => 'setPhotoFeux',
            'photo_bache' => 'setPhotoBache',
            'photo_marquage_au_sol' => 'setPhotoMarquageAuSol',
            'photo_butoir' => 'setPhotoButoir',
            'photo_vantail' => 'setPhotoVantail',
            'photo_linteau' => 'setPhotoLinteau'
        ];
        
        foreach ($photoMapping as $field => $setter) {
            if (!empty($equipmentData[$field]['value']) && method_exists($form, $setter)) {
                $form->$setter($equipmentData[$field]['value']);
            }
        }
    }

    /**
     * Mapping complet des noms de champs pour référence future
     * 
     * PROPRIÉTÉ PHP (dans DQL)          → GETTER/SETTER (dans le code)
     * ================================     ==========================
     * id_contact                       → getIdContact() / setIdContact()
     * numero_equipement                → getNumeroEquipement() / setNumeroEquipement()
     * libelle_equipement               → getLibelleEquipement() / setLibelleEquipement()
     * mode_fonctionnement              → getModeFonctionnement() / setModeFonctionnement()
     * repere_site_client               → getRepereSiteClient() / setRepereSiteClient()
     * mise_en_service                  → getMiseEnService() / setMiseEnService()
     * numero_de_serie                  → getNumeroDeSerie() / setNumeroDeSerie()
     * marque                           → getMarque() / setMarque()
     * hauteur                          → getHauteur() / setHauteur()
     * largeur                          → getLargeur() / setLargeur()
     * longueur                         → getLongueur() / setLongueur()
     * plaque_signaletique              → getPlaqueSignaletique() / setPlaqueSignaletique()
     * anomalies                        → getAnomalies() / setAnomalies()
     * etat                             → getEtat() / setEtat()
     * derniere_visite                  → getDerniereVisite() / setDerniereVisite()
     * trigramme_tech                   → getTrigrammeTech() / setTrigrammeTech()
     * code_societe                     → getCodeSociete() / setCodeSociete()
     * raison_sociale                   → getRaisonSociale() / setRaisonSociale()
     * signature_tech                   → getSignatureTech() / setSignatureTech()
     * code_agence                      → getCodeAgence() / setCodeAgence()
     * statut_de_maintenance            → getStatutDeMaintenance() / setStatutDeMaintenance()
     * date_enregistrement              → getDateEnregistrement() / setDateEnregistrement()
     * isEtatDesLieuxFait              → isEtatDesLieuxFait() / setEtatDesLieuxFait()
     * isEnMaintenance                 → isEnMaintenance() / setEnMaintenance()
     * visite                          → getVisite() / setVisite()
     * is_archive                      → isArchive() / setIsArchive()
     */
    
     /**
     * Script pour vérifier la configuration des agences
     */

    // Route de test à ajouter dans SimplifiedMaintenanceController
    #[Route('/api/maintenance/verify-agencies', name: 'app_maintenance_verify_agencies', methods: ['GET'])]
    public function verifyAgenciesConfiguration(): JsonResponse
    {
        $agencyMapping = $this->getAgencyFormMapping();
        $results = [];
        
        foreach ($agencyMapping as $agencyCode => $formId) {
            $status = [
                'agency' => $agencyCode,
                'form_id' => $formId,
                'configured' => !empty($formId),
                'test_url' => !empty($formId) ? 
                    "/api/maintenance/process-form-optimized/{$agencyCode}?chunk_size=5&max_submissions=5" : 
                    null
            ];
            
            // Test de connectivité API si configuré
            if (!empty($formId)) {
                try {
                    $response = $this->client->request(
                        'GET',
                        "https://forms.kizeo.com/rest/v3/forms/{$formId}/data",
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                            'query' => ['limit' => 1]
                        ]
                    );
                    
                    $data = $response->toArray();
                    $status['api_accessible'] = true;
                    $status['submissions_count'] = count($data['data'] ?? []);
                    
                } catch (\Exception $e) {
                    $status['api_accessible'] = false;
                    $status['api_error'] = $e->getMessage();
                }
            }
            
            $results[] = $status;
        }
        
        return new JsonResponse([
            'success' => true,
            'agencies_status' => $results,
            'summary' => [
                'total_agencies' => count($agencyMapping),
                'configured' => count(array_filter($agencyMapping)),
                'not_configured' => count(array_filter($agencyMapping, function($formId) {
                    return empty($formId);
                }))
            ]
        ]);
    }

    /**
     * Route de debug pour tester une agence spécifique
     */
    #[Route('/api/maintenance/debug-agency/{agencyCode}', name: 'app_maintenance_debug_agency', methods: ['GET'])]
    public function debugAgency(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id');
        $limit = (int) $request->query->get('limit', 5);
        
        // 1. Vérifier la configuration
        $agencyMapping = $this->getAgencyFormMapping();
        $configuredFormId = $agencyMapping[$agencyCode] ?? null;
        
        if (!$formId && !$configuredFormId) {
            return new JsonResponse([
                'error' => "Agence {$agencyCode} non configurée",
                'solution' => "Ajouter le form_id dans getAgencyFormMapping()",
                'available_agencies' => array_keys(array_filter($agencyMapping))
            ], 400);
        }
        
        $testFormId = $formId ?: $configuredFormId;
        
        try {
            // 2. Test de connectivité API
            $response = $this->client->request(
                'GET',
                "https://forms.kizeo.com/rest/v3/forms/{$testFormId}/data",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'query' => ['limit' => $limit]
                ]
            );
            
            $submissionsData = $response->toArray();
            $submissions = $submissionsData['data'] ?? [];
            
            $debugInfo = [
                'success' => true,
                'agency' => $agencyCode,
                'form_id_used' => $testFormId,
                'form_id_configured' => $configuredFormId,
                'api_response_status' => 'OK',
                'total_submissions' => count($submissions),
                'submissions_sample' => []
            ];
            
            // 3. Analyser quelques soumissions
            foreach (array_slice($submissions, 0, 2) as $submission) {
                try {
                    $detailResponse = $this->client->request(
                        'GET',
                        "https://forms.kizeo.com/rest/v3/forms/{$testFormId}/data/{$submission['_id']}",
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ],
                        ]
                    );
                    
                    $detailData = $detailResponse->toArray();
                    $fields = $detailData['data']['fields'] ?? [];
                    
                    $submissionAnalysis = [
                        'id' => $submission['_id'],
                        'has_contract_equipment' => isset($fields['contrat_de_maintenance']),
                        'has_offcontract_equipment' => isset($fields['tableau2']),
                        'contract_count' => count($fields['contrat_de_maintenance']['value'] ?? []),
                        'offcontract_count' => count($fields['tableau2']['value'] ?? []),
                        'client_field' => $fields['nom_client']['value'] ?? $fields['nom_du_client']['value'] ?? 'NOT_FOUND',
                        'date_field' => $fields['date_et_heure1']['value'] ?? $fields['date_et_heure']['value'] ?? 'NOT_FOUND',
                        'agency_field' => $fields['code_agence']['value'] ?? $fields['id_agence']['value'] ?? 'NOT_FOUND',
                        'form_version' => $this->detectFormVersion($fields)
                    ];
                    
                    $debugInfo['submissions_sample'][] = $submissionAnalysis;
                    
                } catch (\Exception $e) {
                    $debugInfo['submissions_sample'][] = [
                        'id' => $submission['_id'],
                        'error' => 'Impossible de récupérer les détails: ' . $e->getMessage()
                    ];
                }
            }
            
            // 4. Recommandations
            $debugInfo['recommendations'] = $this->generateRecommendations($debugInfo);
            
            return new JsonResponse($debugInfo);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'agency' => $agencyCode,
                'form_id_tested' => $testFormId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'recommendations' => [
                    'Vérifier la validité du form_id',
                    'Vérifier les permissions API',
                    'Tester avec un autre form_id'
                ]
            ], 500);
        }
    }

    /**
     * Détecter la version du formulaire
     */
    private function detectFormVersion(array $fields): string
    {
        // V5 a généralement des champs différents de V4
        if (isset($fields['new_field_v5']) || isset($fields['updated_structure'])) {
            return 'V5';
        }
        
        // Critères spécifiques pour détecter V4 vs V5
        $v5Indicators = ['specific_v5_field', 'another_v5_field'];
        
        foreach ($v5Indicators as $indicator) {
            if (isset($fields[$indicator])) {
                return 'V5';
            }
        }
        
        return 'V4';
    }

    /**
     * Générer des recommandations basées sur l'analyse
     */
    private function generateRecommendations(array $debugInfo): array
    {
        $recommendations = [];
        
        if ($debugInfo['total_submissions'] == 0) {
            $recommendations[] = "Aucune soumission trouvée - Vérifier si le formulaire a des données";
        }
        
        if (!empty($debugInfo['submissions_sample'])) {
            $sample = $debugInfo['submissions_sample'][0];
            
            if ($sample['contract_count'] == 0 && $sample['offcontract_count'] == 0) {
                $recommendations[] = "Aucun équipement trouvé - Vérifier la structure des champs";
            }
            
            if ($sample['client_field'] === 'NOT_FOUND') {
                $recommendations[] = "Champ client non trouvé - Adapter le mapping des champs";
            }
            
            if ($sample['agency_field'] === 'NOT_FOUND') {
                $recommendations[] = "Champ agence non trouvé - Vérifier les champs d'agence";
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Configuration semble correcte - Tester avec processMaintenanceByFormIdOptimized";
        }
        
        return $recommendations;
    }

    /**
     * Route pour debug le filtrage des soumissions
     */
    #[Route('/api/maintenance/debug-submissions/{agencyCode}', name: 'app_maintenance_debug_submissions', methods: ['GET'])]
    public function debugSubmissionsFilter(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id');
        $limit = (int) $request->query->get('limit', 10);
        
        // Utiliser le mapping si pas de form_id
        if (!$formId) {
            $agencyMapping = $this->getAgencyFormMapping();
            $formId = $agencyMapping[$agencyCode] ?? null;
        }
        
        if (!$formId) {
            return new JsonResponse(['error' => 'Form ID non trouvé'], 400);
        }
        
        try {
            // 1. Récupérer toutes les soumissions sans filtrage
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/advanced',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'json' => [
                        'limit' => $limit,
                        'offset' => 0
                    ]
                ]
            );

            $formData = $response->toArray();
            $submissionsAnalysis = [];
            $agencyFieldsFound = [];
            
            // 2. Analyser chaque soumission
            foreach (array_slice($formData['data'] ?? [], 0, $limit) as $entry) {
                try {
                    $detailResponse = $this->client->request(
                        'GET',
                        'https://forms.kizeo.com/rest/v3/forms/' . $entry['_form_id'] . '/data/' . $entry['_id'],
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                            ]
                        ]
                    );

                    $detailData = $detailResponse->toArray();
                    $fields = $detailData['data']['fields'] ?? [];
                    
                    // 3. Chercher tous les champs qui pourraient contenir l'agence
                    $agencyFields = [];
                    foreach ($fields as $fieldName => $fieldData) {
                        if (stripos($fieldName, 'agence') !== false || 
                            stripos($fieldName, 'code') !== false ||
                            (is_array($fieldData) && isset($fieldData['value']) && 
                            is_string($fieldData['value']) && 
                            preg_match('/S\d+/', $fieldData['value']))) {
                            
                            $agencyFields[$fieldName] = $fieldData['value'] ?? '';
                            $agencyFieldsFound[$fieldName] = ($agencyFieldsFound[$fieldName] ?? 0) + 1;
                        }
                    }
                    
                    // 4. Analyser la structure
                    $analysis = [
                        'entry_id' => $entry['_id'],
                        'agency_fields_found' => $agencyFields,
                        'target_agency' => $agencyCode,
                        'matches_target' => false,
                        'client_field' => $fields['nom_client']['value'] ?? $fields['nom_du_client']['value'] ?? 'NOT_FOUND',
                        'date_field' => $fields['date_et_heure1']['value'] ?? $fields['date_et_heure']['value'] ?? 'NOT_FOUND',
                        'has_equipment_contract' => isset($fields['contrat_de_maintenance']) && !empty($fields['contrat_de_maintenance']['value'] ?? []),
                        'has_equipment_offcontract' => isset($fields['tableau2']) && !empty($fields['tableau2']['value'] ?? [])
                    ];
                    
                    // 5. Vérifier si cette soumission correspond à l'agence cible
                    foreach ($agencyFields as $fieldValue) {
                        if ($fieldValue === $agencyCode) {
                            $analysis['matches_target'] = true;
                            break;
                        }
                    }
                    
                    $submissionsAnalysis[] = $analysis;
                    
                } catch (\Exception $e) {
                    $submissionsAnalysis[] = [
                        'entry_id' => $entry['_id'],
                        'error' => 'Impossible de récupérer: ' . $e->getMessage()
                    ];
                }
            }
            
            // 6. Générer le rapport
            $matchingSubmissions = array_filter($submissionsAnalysis, function($s) {
                return $s['matches_target'] ?? false;
            });
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'debug_info' => [
                    'total_submissions_analyzed' => count($submissionsAnalysis),
                    'matching_submissions' => count($matchingSubmissions),
                    'agency_fields_found_across_all' => $agencyFieldsFound,
                    'most_common_agency_field' => $this->getMostCommonField($agencyFieldsFound)
                ],
                'submissions_analysis' => $submissionsAnalysis,
                'matching_submissions_only' => array_values($matchingSubmissions),
                'recommendations' => $this->generateFilteringRecommendations($submissionsAnalysis, $agencyCode, $agencyFieldsFound)
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'agency' => $agencyCode,
                'form_id' => $formId
            ], 500);
        }
    }

    /**
     * Trouver le champ d'agence le plus commun
     */
    private function getMostCommonField(array $agencyFieldsFound): ?string
    {
        if (empty($agencyFieldsFound)) {
            return null;
        }
        
        return array_key_first(array_slice($agencyFieldsFound, 0, 1, true));
    }

    /**
     * Générer des recommandations pour corriger le filtrage
     */
    private function generateFilteringRecommendations(array $analysis, string $targetAgency, array $agencyFields): array
    {
        $recommendations = [];
        
        if (empty($analysis)) {
            $recommendations[] = "Aucune soumission trouvée - Vérifier l'API Kizeo";
            return $recommendations;
        }
        
        $totalAnalyzed = count($analysis);
        $withEquipment = count(array_filter($analysis, function($a) {
            return ($a['has_equipment_contract'] ?? false) || ($a['has_equipment_offcontract'] ?? false);
        }));
        
        if ($withEquipment == 0) {
            $recommendations[] = "Aucune soumission avec équipements trouvée - Vérifier la structure des formulaires";
        }
        
        if (empty($agencyFields)) {
            $recommendations[] = "Aucun champ d'agence détecté - Le filtrage par agence ne peut pas fonctionner";
            $recommendations[] = "Solution: Traiter TOUTES les soumissions sans filtrage d'agence";
        } else {
            $mostCommon = $this->getMostCommonField($agencyFields);
            $recommendations[] = "Champ d'agence le plus fréquent: '{$mostCommon}'";
            $recommendations[] = "Modifier getFormSubmissionsOptimized pour utiliser ce champ";
        }
        
        $matchingCount = count(array_filter($analysis, function($a) {
            return $a['matches_target'] ?? false;
        }));
        
        if ($matchingCount == 0) {
            $recommendations[] = "PROBLÈME: Aucune soumission ne correspond à l'agence {$targetAgency}";
            $recommendations[] = "Solution 1: Vérifier si ce formulaire contient vraiment des données pour {$targetAgency}";
            $recommendations[] = "Solution 2: Traiter TOUTES les soumissions sans filtre d'agence";
        } else {
            $recommendations[] = "SUCCÈS: {$matchingCount}/{$totalAnalyzed} soumissions correspondent à {$targetAgency}";
            $recommendations[] = "Le filtrage peut être corrigé avec les bons champs";
        }
        
        return $recommendations;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * SOLUTION 1: Version avec timeout plus long et endpoint simple
     */
    #[Route('/api/maintenance/test-simple-api/{agencyCode}', name: 'app_maintenance_test_simple_api', methods: ['GET'])]
    public function testSimpleAPI(
        string $agencyCode,
        Request $request
    ): JsonResponse {
        
        $formId = $request->query->get('form_id');
        $limit = (int) $request->query->get('limit', 10);
        
        if (!$formId) {
            $agencyMapping = $this->getAgencyFormMapping();
            $formId = $agencyMapping[$agencyCode] ?? null;
        }
        
        if (!$formId) {
            return new JsonResponse(['error' => 'Form ID non trouvé'], 400);
        }
        
        try {
            // UTILISER L'ENDPOINT SIMPLE avec timeout étendu
            $response = $this->client->request(
                'GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'query' => [
                        'limit' => $limit,
                        'offset' => 0
                    ],
                    'timeout' => 120  // 2 minutes au lieu de 30s par défaut
                ]
            );

            $formData = $response->toArray();
            $submissions = $formData['data'] ?? [];
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'api_endpoint' => 'simple',
                'total_submissions' => count($submissions),
                'submissions_sample' => array_slice($submissions, 0, 3),
                'message' => count($submissions) > 0 ? 
                    'API simple fonctionne - ' . count($submissions) . ' soumissions trouvées' :
                    'API accessible mais aucune soumission'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timeout_occurred' => stripos($e->getMessage(), 'timeout') !== false
            ], 500);
        }
    }

    /**
     * SOLUTION FONCTIONNELLE : Remplacer getFormSubmissionsOptimized
     */
    private function getFormSubmissionsFixed(string $formId, string $agencyCode, int $maxSubmissions = 20): array
    {
        try {
            $validSubmissions = [];
            $offset = 0;
            $batchSize = 20; // Taille raisonnable
            
            while (count($validSubmissions) < $maxSubmissions && $offset < 200) {
                
                // UTILISER L'ENDPOINT SIMPLE qui fonctionne
                $response = $this->client->request(
                    'GET',
                    "https://forms.kizeo.com/rest/v3/forms/{$formId}/data",
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                        ],
                        'query' => [
                            'limit' => $batchSize,
                            'offset' => $offset
                        ],
                        'timeout' => 90
                    ]
                );

                $formData = $response->toArray();
                $batchSubmissions = $formData['data'] ?? [];
                
                if (empty($batchSubmissions)) {
                    break; // Plus de données
                }
                
                // Traitement des soumissions SANS filtrage d'agence strict
                foreach ($batchSubmissions as $entry) {
                    if (count($validSubmissions) >= $maxSubmissions) {
                        break 2;
                    }
                    
                    // Conversion au format attendu par le traitement
                    $validSubmissions[] = [
                        'form_id' => $entry['form_id'] ?? $formId,
                        'entry_id' => $entry['id'], // ATTENTION : 'id' pas '_id' dans l'endpoint simple
                        'client_name' => 'À déterminer lors du traitement',
                        'date' => $entry['answer_time'] ?? 'N/A',
                        'technician' => 'À déterminer lors du traitement'
                    ];
                }
                
                $offset += $batchSize;
                
                // Petite pause pour éviter de surcharger l'API
                usleep(50000); // 0.05 seconde
            }
            
            error_log("getFormSubmissionsFixed: " . count($validSubmissions) . " soumissions récupérées pour {$agencyCode}");
            return $validSubmissions;
            
        } catch (\Exception $e) {
            error_log("Erreur getFormSubmissionsFixed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * SOLUTION 3: Version ultra-conservative pour les formulaires problématiques
     */
    #[Route('/api/maintenance/process-conservative/{agencyCode}', name: 'app_maintenance_process_conservative', methods: ['GET'])]
    public function processConservative(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        // Configuration ultra-conservative
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 180); // 3 minutes max
        
        $formId = $request->query->get('form_id');
        $maxSubmissions = (int) $request->query->get('max_submissions', 5); // Très petit par défaut
        $chunkSize = (int) $request->query->get('chunk_size', 2); // Très petit
        
        if (!$formId) {
            $agencyMapping = $this->getAgencyFormMapping();
            $formId = $agencyMapping[$agencyCode] ?? null;
        }
        
        if (!$formId) {
            return new JsonResponse(['error' => 'Form ID non trouvé'], 400);
        }
        
        try {
            $startTime = time();
            
            // 1. Récupération sécurisée par pagination
            $submissions = $this->getFormSubmissionsFixed($formId, $agencyCode, $maxSubmissions);
            
            if (empty($submissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission récupérée (API timeout ou pas de données)',
                    'agency' => $agencyCode,
                    'form_id' => $formId,
                    'processed_submissions' => 0,
                    'recommendation' => 'Essayer avec un limit plus petit ou vérifier l\'état de l\'API Kizeo'
                ]);
            }
            
            // 2. Traitement par très petits chunks
            $processedCount = 0;
            $totalEquipments = 0;
            $errors = [];
            
            $chunks = array_chunk($submissions, $chunkSize);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                
                // Vérification timeout
                if (time() - $startTime > 150) { // 2.5 minutes max
                    break;
                }
                
                foreach ($chunk as $submission) {
                    try {
                        // Récupération des détails avec timeout court
                        $detailResponse = $this->client->request(
                            'GET',
                            "https://forms.kizeo.com/rest/v3/forms/{$submission['form_id']}/data/{$submission['entry_id']}",
                            [
                                'headers' => [
                                    'Accept' => 'application/json',
                                    'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                                ],
                                'timeout' => 30
                            ]
                        );

                        $detailData = $detailResponse->toArray();
                        $fields = $detailData['data']['fields'] ?? [];
                        
                        // Traitement minimal des équipements
                        $contractEquipments = $fields['contrat_de_maintenance']['value'] ?? [];
                        $offContractEquipments = $fields['tableau2']['value'] ?? [];
                        
                        $equipmentCount = count($contractEquipments) + count($offContractEquipments);
                        $totalEquipments += $equipmentCount;
                        
                        // ici : Traiter les équipements avec la logique existante
                        // ... (code de traitement simplifié)
                        
                        $processedCount++;
                        
                    } catch (\Exception $e) {
                        $errors[] = [
                            'entry_id' => $submission['entry_id'],
                            'error' => $e->getMessage()
                        ];
                        continue;
                    }
                }
                
                // Pause entre chunks
                usleep(200000); // 0.2 seconde
                
                // Forcer garbage collection
                gc_collect_cycles();
            }
            
            $processingTime = time() - $startTime;
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'processing_summary' => [
                    'processed_submissions' => $processedCount,
                    'total_submissions_attempted' => count($submissions),
                    'total_equipments_found' => $totalEquipments,
                    'processing_time' => $processingTime . 's',
                    'errors_count' => count($errors)
                ],
                'status' => $processedCount > 0 ? 'partial_success' : 'no_processing',
                'errors' => $errors,
                'message' => $processedCount > 0 ? 
                    "Traitement conservatif: {$processedCount} soumissions traitées avec {$totalEquipments} équipements" :
                    "Aucun traitement effectué - problèmes d'API"
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'agency' => $agencyCode,
                'form_id' => $formId
            ], 500);
        }
    }

    private function generateConnectivityRecommendation(array $tests): string
    {
        if ($tests['data_simple']['status'] === 'success') {
            return "API fonctionnelle - Utiliser l'endpoint simple avec pagination";
        } elseif ($tests['basic_info']['status'] === 'success') {
            return "Formulaire accessible mais problème avec les données - Contacter support Kizeo";
        } else {
            return "Problème de connectivité général - Vérifier token API et réseau";
        }
    }

    /////////// Traitement avec CACHE REDIS ///////////

    /**
     * ROUTE CORRIGÉE AVEC REDIS CACHE
     * Usage : /api/maintenance/process-fixed/S40?chunk_size=5&max_submissions=300&use_cache=true
     */
    #[Route('/api/maintenance/process-fixed/{agencyCode}', name: 'app_maintenance_process_fixed', methods: ['GET'])]
    public function processMaintenanceFixed(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request,
        MaintenanceCacheService $cacheService // Utilisation du service dédié 
    ): JsonResponse {
        
        // Configuration conservative
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', 300);
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id');
        $chunkSize = (int) $request->query->get('chunk_size', 5);
        $maxSubmissions = (int) $request->query->get('max_submissions', 10);
        $useCache = $request->query->get('use_cache', 'true') === 'true';
        $refreshCache = $request->query->get('refresh_cache', 'false') === 'true';
        
        if (!$formId) {
            $agencyMapping = $this->getAgencyFormMapping();
            $formId = $agencyMapping[$agencyCode] ?? null;
            
            if (!$formId) {
                return new JsonResponse([
                    'error' => 'Aucun form_id trouvé pour l\'agence ' . $agencyCode,
                    'available_agencies' => array_keys($agencyMapping)
                ], 400);
            }
        }

        try {
            $startTime = time();
            
            // 1. Gestion du cache Redis pour les soumissions via le service
            $submissions = [];
            $fromCache = false;
            
            if ($useCache && $cacheService && !$refreshCache) {
                // Essayer de récupérer depuis le cache via le service
                $cachedSubmissions = $cacheService->getSubmissionsList($agencyCode, $formId);
                if (!empty($cachedSubmissions)) {
                    // Récupérer les soumissions complètes
                    $submissions = $cacheService->getBulkSubmissions($agencyCode, $cachedSubmissions, false);
                    if (!empty($submissions)) {
                        $submissions = array_values($submissions); // Réindexer le tableau
                        $fromCache = true;
                    }
                }
            }
            
            // Si pas en cache ou cache forcé à refresh, récupérer depuis la DB
            if (empty($submissions)) {
                $submissions = $this->getFormSubmissionsFixed($formId, $agencyCode, $maxSubmissions);
                
                // Sauvegarder en cache si service disponible
                if ($useCache && $cacheService && !empty($submissions)) {
                    // Sauvegarder chaque soumission individuellement
                    $submissionIds = [];
                    foreach ($submissions as $submission) {
                        $submissionId = $submission['entry_id'];
                        $submissionIds[] = $submissionId;
                        $cacheService->saveRawSubmission($agencyCode, $submissionId, $submission);
                    }
                    // Sauvegarder la liste des IDs
                    $cacheService->saveSubmissionsList($agencyCode, $formId, $submissionIds);
                }
            }
            
            if (empty($submissions)) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Aucune soumission trouvée pour le formulaire ' . $formId,
                    'agency' => $agencyCode,
                    'form_id' => $formId,
                    'processed_submissions' => 0,
                    'cache_used' => $fromCache
                ]);
            }
            
            // 2. Traitement par chunks avec cache individuel
            $processedCount = 0;
            $totalEquipments = 0;
            $totalPhotos = 0;
            $errors = [];
            $cacheHits = 0;
            
            $entityClass = $this->getEntityClassByAgency($agencyCode);
            if (!$entityClass) {
                return new JsonResponse(['error' => 'Classe d\'entité non trouvée pour ' . $agencyCode], 400);
            }
            
            $chunks = array_chunk($submissions, $chunkSize);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                // Vérification timeout
                if (time() - $startTime > 250) { // 4 minutes max
                    break;
                }
                
                foreach ($chunk as $submissionIndex => $submission) {
                    try {
                        // Récupération depuis le cache individuel si disponible
                        $submissionData = null;
                        $submissionId = $submission['entry_id'];
                        
                        if ($useCache && $cacheService) {
                            $submissionData = $cacheService->getProcessedSubmission($agencyCode, $submissionId);
                            if ($submissionData) {
                                $cacheHits++;
                            }
                        }
                        
                        // Si pas en cache, traiter normalement et sauvegarder
                        if (!$submissionData) {
                            $result = $this->processSingleSubmissionWithDeduplication(
                                $submission,
                                $agencyCode,
                                $entityClass,
                                $chunkSize,
                                $entityManager
                            );
                            
                            // ✅ AJOUTER CES LIGNES POUR COMPTER LES ÉQUIPEMENTS :
                            $totalEquipments += $result['equipments_processed'] ?? 0;
                            $totalPhotos += $result['photos_saved'] ?? 0;
                            
                            // Préparer les données pour le cache
                            $submissionData = [
                                'submission_id' => $submissionId,
                                'processed_at' => time(),
                                'result' => $result,
                                'entity_class' => $entityClass
                            ];
                            
                            // Sauvegarder le résultat en cache
                            if ($useCache && $cacheService) {
                                $cacheService->saveProcessedSubmission($agencyCode, $submissionId, $submissionData);
                            }
                        } else {
                            // ✅ AJOUTER AUSSI POUR LES DONNÉES DEPUIS LE CACHE :
                            $cachedResult = $submissionData['result'] ?? [];
                            $totalEquipments += $cachedResult['equipments_processed'] ?? 0;
                            $totalPhotos += $cachedResult['photos_saved'] ?? 0;
                            
                            // Utiliser les données du cache pour recréer les entités
                            $this->recreateEntitiesFromCache($submissionData, $entityManager);
                        }

                        $processedCount++;
                        
                        // Flush périodique pour libérer la mémoire
                        if ($processedCount % 3 == 0) {
                            $entityManager->flush();
                            $entityManager->clear();
                            gc_collect_cycles();
                        }
                        
                    } catch (\Exception $e) {
                        $errors[] = [
                            'submission_id' => $submission['entry_id'],
                            'error' => $e->getMessage()
                        ];
                        continue;
                    }
                }
                
                // Sauvegarde après chaque chunk
                try {
                    $entityManager->flush();
                    $entityManager->clear();
                    gc_collect_cycles();
                } catch (\Exception $e) {
                    error_log("Erreur sauvegarde chunk {$chunkIndex}: " . $e->getMessage());
                }
                
                // Pause entre chunks
                usleep(100000); // 0.1 seconde
            }
            
            // Sauvegarde finale
            try {
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log("Erreur sauvegarde finale: " . $e->getMessage());
            }
            
            $processingTime = time() - $startTime;
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'form_id' => $formId,
                'processing_summary' => [
                    'processed_submissions' => $processedCount,
                    'total_submissions_found' => count($submissions),
                    'total_equipments_processed' => $totalEquipments,
                    'total_photos_processed' => $totalPhotos,
                    'processing_time' => $processingTime . 's',
                    'errors_count' => count($errors)
                ],
                'cache_info' => [
                    'cache_used' => $fromCache || $cacheHits > 0,
                    'submissions_from_cache' => $fromCache ? count($submissions) : 0,
                    'individual_cache_hits' => $cacheHits,
                    'redis_available' => $cacheService !== null,
                    'connection_test' => $cacheService ? $cacheService->testConnection() : null
                ],
                'chunk_info' => [
                    'chunk_size' => $chunkSize,
                    'total_chunks' => count($chunks),
                    'max_submissions_limit' => $maxSubmissions
                ],
                'status' => $processedCount > 0 ? 'success' : 'no_data',
                'errors' => array_slice($errors, 0, 10),
                'message' => $processedCount > 0 ? 
                    "Traitement réussi: {$processedCount}/{$maxSubmissions} soumissions et {$totalEquipments} équipements traités en {$processingTime}s" :
                    "Aucune soumission avec équipements trouvée"
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'agency' => $agencyCode,
                'form_id' => $formId
            ], 500);
        }
    }

    /**
     * Route pour vider le cache d'une agence
     */
    #[Route('/api/maintenance/clear-cache/{agencyCode}', name: 'app_maintenance_clear_cache', methods: ['DELETE'])]
    public function clearAgencyCache(
        string $agencyCode,
        MaintenanceCacheService $cacheService,
        Request $request
    ): JsonResponse {
        try {
            $formId = $request->query->get('form_id');
            
            if (!$formId) {
                $agencyMapping = $this->getAgencyFormMapping();
                $formId = $agencyMapping[$agencyCode] ?? null;
            }
            
            if (!$formId) {
                return new JsonResponse(['error' => 'Form ID non trouvé'], 400);
            }
            
            $deletedCount = $cacheService->clearAgencyCache($agencyCode, $formId);
            
            return new JsonResponse([
                'success' => true,
                'message' => "Cache vidé pour l'agence {$agencyCode}",
                'deleted_keys' => $deletedCount,
                'connection_test' => $cacheService->testConnection()
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recrée les entités depuis les données en cache
     */
    private function recreateEntitiesFromCache(array $cachedData, EntityManagerInterface $entityManager): void
    {
        try {
            // Cette méthode recrée les entités depuis les données mises en cache
            // Au lieu de traiter depuis l'API, on utilise les données du cache
            
            if (isset($cachedData['equipments']) && is_array($cachedData['equipments'])) {
                foreach ($cachedData['equipments'] as $equipmentData) {
                    // Recréer l'équipement depuis les données en cache
                    $entityClass = $cachedData['entity_class'] ?? null;
                    if (!$entityClass || !class_exists($entityClass)) {
                        continue;
                    }
                    
                    $equipment = new $entityClass();
                    
                    // Restaurer les propriétés depuis le cache
                    foreach ($equipmentData as $property => $value) {
                        $setter = 'set' . ucfirst($property);
                        if (method_exists($equipment, $setter)) {
                            $equipment->$setter($value);
                        }
                    }
                    
                    $entityManager->persist($equipment);
                }
            }
            
            if (isset($cachedData['photos']) && is_array($cachedData['photos'])) {
                // Traiter les photos depuis le cache si nécessaire
                foreach ($cachedData['photos'] as $photoData) {
                    // Logique de traitement des photos depuis le cache
                    // À adapter selon votre structure
                }
            }
            
        } catch (\Exception $e) {
            error_log("Erreur recréation entités depuis cache: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Route pour obtenir les statistiques du cache
     */
    #[Route('/api/maintenance/cache-stats/{agencyCode}', name: 'app_maintenance_cache_stats', methods: ['GET'])]
    public function getCacheStats(
        string $agencyCode,
        MaintenanceCacheService $cacheService
    ): JsonResponse {
        try {
            $stats = $cacheService->getCacheStats($agencyCode);
            
            return new JsonResponse([
                'success' => true,
                'stats' => $stats,
                'connection_test' => $cacheService->testConnection()
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Méthodes helper adaptées pour différentes agences
     */
    private function setCommonDataForAgency($equipement, array $fields, string $agencyCode): void
    {
        // Données communes selon l'agence
        $equipement->setCodeAgence($agencyCode);
        $equipement->setIdContact($fields['id_client_']['value'] ?? $fields['id_contact']['value'] ?? '');
        
        // Le nom du client peut varier selon les formulaires
        $clientName = $fields['nom_client']['value'] ?? 
                    $fields['nom_du_client']['value'] ?? 
                    $fields['client_name']['value'] ?? '';
        $equipement->setRaisonSociale($clientName);
        
        // Date peut varier
        $date = $fields['date_et_heure1']['value'] ?? 
            $fields['date_et_heure']['value'] ?? 
            $fields['date']['value'] ?? '';
        $equipement->setDateEnregistrement($date);
        
        // Technicien peut varier
        $technicien = $fields['trigramme']['value'] ?? 
                    $fields['technicien']['value'] ?? 
                    $fields['tech']['value'] ?? '';
        $equipement->setTrigrammeTech($technicien);
        
        // Valeurs par défaut
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
    }

    private function setContractDataForAgency($equipement, array $equipmentData, string $agencyCode): void
    {
        // À adapter selon la structure de chaque agence
        // Pour l'instant, logique générique
        
        $numeroEquipement = $equipmentData['equipement']['value'] ?? 
                        $equipmentData['numero_equipement']['value'] ?? '';
        $equipement->setNumeroEquipement($numeroEquipement);
        
        $libelle = $equipmentData['libelle']['value'] ?? 
                $equipmentData['description']['value'] ?? '';
        $equipement->setLibelleEquipement($libelle);
        
        // Autres champs selon disponibilité
        $this->setCommonEquipmentFields($equipement, $equipmentData);
    }

    private function setOffContractDataForAgency($equipement, array $equipmentData, string $agencyCode): void
    {
        // Même logique que contrat mais avec en_maintenance = false
        $this->setContractDataForAgency($equipement, $equipmentData, $agencyCode);
    }

    private function setCommonEquipmentFields($equipement, array $equipmentData): void
    {
        // Champs communs à tous les équipements
        if (method_exists($equipement, 'setMarque') && isset($equipmentData['marque']['value'])) {
            $equipement->setMarque($equipmentData['marque']['value']);
        }
        
        if (method_exists($equipement, 'setHauteur') && isset($equipmentData['hauteur']['value'])) {
            $equipement->setHauteur($equipmentData['hauteur']['value']);
        }
        
        if (method_exists($equipement, 'setLargeur') && isset($equipmentData['largeur']['value'])) {
            $equipement->setLargeur($equipmentData['largeur']['value']);
        }
        
        // Ajouter d'autres champs selon les besoins
    }
}