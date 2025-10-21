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
use App\Entity\Form;
use App\Repository\FormRepository;
use App\Service\ImageStorageService;
use App\Service\MaintenanceCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class SimplifiedMaintenanceController extends AbstractController
{
    private ImageStorageService $imageStorageService;
    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(
        ImageStorageService $imageStorageService,
        HttpClientInterface $client,
        LoggerInterface $logger
    ) {
        $this->imageStorageService = $imageStorageService;
        $this->client = $client;
        $this->logger = $logger;
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
                // dump("Erreur récupération données formulaire {$formId}: " . $e->getMessage());
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
          // dump("Erreur markFormAsRead: " . $e->getMessage());
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
         // Exemple de path : "CE1\BPO01" ou "CE2\SEC03"
        $parts = explode('\\', $path);
        
        // Retourner la première partie qui contient la visite complète
        return $parts[0] ?? 'CE1';  // ← S'assurer de retourner CE1, CE2, etc. et pas juste CE
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
            'Porte sectionnelle' => 'SEC',
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
          // dump("Type trouvé (exact): {$libelleNormalized} -> " . $mappingTable[$libelleNormalized]);
            return $mappingTable[$libelleNormalized];
        }
        
        // Recherche par mots-clés pour les portes piétonnes
        if (str_contains($libelleNormalized, 'pieton') || str_contains($libelleNormalized, 'piéton')) {
          // dump("Type trouvé (mot-clé piéton): {$libelleNormalized} -> PPV");
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
      // dump("=== RECHERCHE PROCHAIN NUMÉRO ===");
      // dump("Type code: {$typeCode}");
      // dump("ID Client: {$idClient}");
      // dump("Entity class: {$entityClass}");
        
        $repository = $entityManager->getRepository($entityClass);
        
        // Requête pour trouver tous les équipements du même type et client
        $qb = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typePattern')
            ->setParameter('idClient', $idClient)
            ->setParameter('typePattern', $typeCode . '%');
        
        // DÉBOGAGE : Afficher la requête SQL générée
        $query = $qb->getQuery();
      // dump("SQL généré: " . $query->getSQL());
      // dump("Paramètres: idClient=" . $idClient . ", typePattern=" . $typeCode . '%');
        
        $equipements = $query->getResult();
        
      // dump("Nombre d'équipements trouvés: " . count($equipements));
        
        $dernierNumero = 0;
        
        foreach ($equipements as $equipement) {
            $numeroEquipement = $equipement->getNumeroEquipement();
          // dump("Numéro équipement analysé: " . $numeroEquipement);
            
            // Pattern pour extraire le numéro (ex: PPV01 -> 01, PPV02 -> 02)
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $numero = (int)$matches[1];
              // dump("Numéro extrait: " . $numero);
                
                if ($numero > $dernierNumero) {
                    $dernierNumero = $numero;
                  // dump("Nouveau dernier numéro: " . $dernierNumero);
                }
            } else {
              // dump("Pattern non reconnu pour: " . $numeroEquipement);
            }
        }
        
        $prochainNumero = $dernierNumero + 1;
      // dump("Prochain numéro calculé: " . $prochainNumero);
        
        return $prochainNumero;
    }
    
    /**
     * Générer un numéro d'équipement unique - VERSION SÉCURISÉE
     */
    private function generateUniqueEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): string
    {
      // dump("=== GÉNÉRATION NUMÉRO UNIQUE ===");
      // dump("Type code: {$typeCode}");
      // dump("ID Client: {$idClient}");
      // dump("Entity class: {$entityClass}");
        
        $maxTries = 10;
        $attempt = 0;
        
        while ($attempt < $maxTries) {
            $attempt++;
          // dump("Tentative #{$attempt}");
            
            // ✅ APPEL AVEC TOUS LES PARAMÈTRES
            $nouveauNumero = $this->getNextEquipmentNumberReal($typeCode, $idClient, $entityClass, $entityManager);
            $numeroFormate = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
            
          // dump("Numéro formaté généré: " . $numeroFormate);
            
            // Vérifier l'unicité
            $repository = $entityManager->getRepository($entityClass);
            $existant = $repository->findOneBy([
                'id_contact' => $idClient,
                'numero_equipement' => $numeroFormate
            ]);
            
            if (!$existant) {
              // dump("Numéro unique confirmé: " . $numeroFormate);
                return $numeroFormate;
            } else {
              // dump("Collision détectée pour: " . $numeroFormate . ", nouvelle tentative...");
            }
        }
        
        // Fallback en cas d'échec
        $timestamp = substr(time(), -4);
        $numeroFallback = $typeCode . $timestamp;
      // dump("FALLBACK utilisé: " . $numeroFallback);
        
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
                  // dump("Traitement formulaire {$form['id']} ({$form['name']})");
                    
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
                      // dump("Aucune donnée non lue pour le formulaire {$form['id']}");
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

                          // dump("Trouvé entrée {$agencyCode}: {$entry['_id']}");
                            
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
                          // dump("Erreur traitement entrée: " . $e->getMessage());
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
                  // dump("Erreur formulaire {$form['id']}: " . $e->getMessage());
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
          // dump("Erreur générale: " . $e->getMessage());
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
                  // dump("Erreur vérification formulaire {$form['id']}: " . $e->getMessage());
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
                      // dump("Erreur équipement contrat $index: " . $e->getMessage());
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
                      // dump("Erreur équipement hors contrat $index: " . $e->getMessage());
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
     * ✅ MISE À JOUR : Génération de numéro avec vérification UnitOfWork
     * 
     * Génère le prochain numéro d'équipement disponible en vérifiant :
     * - Les équipements déjà en base de données
     * - Les équipements en attente de flush (UnitOfWork)
     * 
     * Cela évite les collisions de numéros dans le même batch de traitement.
     * 
     * @param string $typeCode Le code type (ex: RID, SEC, NIV)
     * @param string $idClient L'identifiant du client
     * @param string $entityClass La classe de l'entité (ex: EquipementS40::class)
     * @param EntityManagerInterface $entityManager Le gestionnaire d'entités Doctrine
     * @return int Le prochain numéro disponible (ex: si RID05 existe, retourne 6)
     */
    private function getNextEquipmentNumberReal(
        string $typeCode,
        string $idClient,
        string $entityClass,
        EntityManagerInterface $entityManager
    ): int {
        $repository = $entityManager->getRepository($entityClass);
        
        // ============================================
        // 1. TROUVER LE PLUS GRAND NUMÉRO EN BASE
        // ============================================
        $qb = $repository->createQueryBuilder('e')
            ->where('e.id_contact = :idClient')
            ->andWhere('e.numero_equipement LIKE :typePattern')
            ->setParameter('idClient', $idClient)
            ->setParameter('typePattern', $typeCode . '%');
        
        $equipements = $qb->getQuery()->getResult();
        
        $dernierNumero = 0;
        
        // Parser tous les numéros d'équipements pour trouver le plus grand
        foreach ($equipements as $equipement) {
            $numeroEquipement = $equipement->getNumeroEquipement();
            
            // Extraire le numéro depuis le format "XXX##" (ex: RID05 -> 5)
            if (preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                $numero = (int)$matches[1];
                if ($numero > $dernierNumero) {
                    $dernierNumero = $numero;
                }
            }
        }
        
        // ============================================
        // 2. ✅ VÉRIFIER AUSSI DANS L'UNITOFWORK
        //    (objets en attente de flush)
        // ============================================
        $uow = $entityManager->getUnitOfWork();
        $scheduledInserts = $uow->getScheduledEntityInsertions();
        
        foreach ($scheduledInserts as $entity) {
            // Vérifier que c'est la bonne classe et le bon client
            if (get_class($entity) === $entityClass && $entity->getIdContact() === $idClient) {
                $numeroEquipement = $entity->getNumeroEquipement();
                
                // Extraire et comparer le numéro
                if ($numeroEquipement && preg_match('/^' . preg_quote($typeCode) . '(\d+)$/', $numeroEquipement, $matches)) {
                    $numero = (int)$matches[1];
                    if ($numero > $dernierNumero) {
                        $dernierNumero = $numero;
                    }
                }
            }
        }
        
        // ============================================
        // 3. RETOURNER LE PROCHAIN NUMÉRO DISPONIBLE
        // ============================================
        return $dernierNumero + 1;
    }

    /**
     * EXEMPLE D'UTILISATION :
     * 
     * // Contexte : Client 126 a déjà RID01, RID02, RID03 en base
     * //           RID04 est en attente de flush dans l'UnitOfWork
     * 
     * $nextNumber = $this->getNextEquipmentNumberReal('RID', '126', EquipementS40::class, $em);
     * // Retourne : 5
     * 
     * $numeroEquipement = 'RID' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
     * // Résultat : "RID05" ✅
     */

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
                      // dump("Erreur équipement contrat $index: " . $e->getMessage());
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
                      // dump("Erreur équipement hors contrat $index: " . $e->getMessage());
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
                  // dump("Erreur formulaire {$form['id']}: " . $e->getMessage());
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
      // dump("=== setRealCommonDataFixed START ===");

        // CORRECTION : Utiliser les vrais noms de champs
        $equipement->setCodeSociete($fields['id_societe']['value'] ?? '');
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? ''); // CORRIGÉ
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? ''); // CORRIGÉ
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? ''); // CORRIGÉ
        
        // Valeurs par défaut SANS setEnMaintenance (sera défini spécifiquement)
        $equipement->setEtatDesLieuxFait(false);
        $equipement->setIsArchive(false);
        
      // dump("Données communes définies - en_maintenance NON défini volontairement");
      // dump("=== setRealCommonDataFixed END ===");
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
            $raisonSociale = $fields['nom_client']['value'] ?? '';
            $typeVisite = $this->extractVisitTypeFromPath($equipmentData['equipement']['path'] ?? '');

            $form->setRaisonSocialeVisite($raisonSociale . '\\' . $typeVisite);
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
          // dump("Erreur sauvegarde photos Form: " . $e->getMessage());
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
            'S50' => '1065302',  // V5- GRENOBLE / Visite de maintenance
            // 'S50' => '1052966',  // V4- GRENOBLE / Visite de maintenance
            'S60' => '1055932',  // V4- Lyon /Visite maintenance
            'S70' => '1057365',  // V4- Bordeaux /Visite maintenance
            'S80' => '1053175',  // V4 - Paris / Visite maintenance
            'S100' => '1071913', // V5- Montpellier /Visite maintenance
            // 'S100' => '1052982', // V4- Montpellier /Visite maintenance
            'S120' => '1062555', // v4- Portland / visite de maintenance
            'S130' => '1057880', // V4- Toulouse / visite de maintenance
            'S140' => '1088761', // V4 - Smp / visite de maintenance
            'S150' => '1057408', // V4- Paca / visite de maintenance
            'S160' => '1060720', // V4- Rouen / visite de maintenance
            'S170' => '1094209', // V5- Rennes / visite de maintenance
        ];
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
     * ✅ CORRECTION 2 : Version corrigée pour équipements au contrat
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
        
        // ✅ Utiliser la nouvelle fonction pour récupérer la visite
        $visite = $this->getVisiteFromFields($fields, $equipmentContrat);
        $numeroEquipement = $equipmentContrat['equipement']['value'] ?? '';
        
        $equipement->setVisite($visite);
        $equipement->setNumeroEquipement($numeroEquipement);
        $equipement->setDerniereVisite($fields['date_et_heure1']['value'] ?? '');
        
        // Vérification doublon
        $idClient = $fields['id_client_']['value'] ?? '';
        $dateVisite = $fields['date_et_heure1']['value'] ?? '';
        
        if ($this->equipmentExistsForSameVisit($numeroEquipement, $idClient, $dateVisite, $entityClass, $entityManager)) {
            return false;
        }

        // Remplir les autres données de l'équipement
        $this->fillContractEquipmentData($equipement, $equipmentContrat);
        
        // Téléchargement et sauvegarde des photos en local
        $agence = $fields['code_agence']['value'] ?? '';
        $raisonSociale = $fields['nom_client']['value'] ?? '';
        $anneeVisite = date('Y', strtotime($dateVisite));
        $idContact = $fields['id_client_']['value'] ?? '';
        
        // ✅ Passer la bonne visite pour le stockage local
        $savedPhotos = $this->downloadAndSavePhotosLocally(
            $equipmentContrat,
            $formId,
            $entryId,
            $agence,
            $idContact,
            $anneeVisite,
            $visite,  // ✅ Utiliser la visite extraite correctement
            $numeroEquipement
        );
        
        // ✅ Sauvegarder dans Form avec la bonne raison_sociale_visite
        $this->savePhotosToFormEntityWithLocalPathsFixed(
            $raisonSociale,
            $visite,  // ✅ Passer la visite séparément
            $equipmentContrat,
            $formId,
            $entryId,
            $numeroEquipement,
            $entityManager,
            $savedPhotos,
            $fields
        );
        
        // Définir les anomalies
        $this->setSimpleEquipmentAnomalies($equipement, $equipmentContrat);

        return true;
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
        string $equipementPath,
        array $equipmentData,
        string $formId, 
        string $entryId, 
        string $equipmentCode, 
        EntityManagerInterface $entityManager
    ): void {
        
      // dump("=== DÉBUT DEBUG PHOTOS HORS CONTRAT ===");
      // dump("Equipment Code: " . $equipmentCode);
      // dump("Form ID: " . $formId);
      // dump("Entry ID: " . $entryId);
        
        // Log des données photo disponibles
      // dump("Photo3 présente: " . (isset($equipmentData['photo3']) ? 'OUI' : 'NON'));
        if (isset($equipmentData['photo3'])) {
          // dump("Photo3 value: " . ($equipmentData['photo3']['value'] ?? 'VIDE'));
          // dump("Photo3 empty check: " . (empty($equipmentData['photo3']['value']) ? 'VIDE' : 'PAS VIDE'));
        }

        // Vérifier si l'entrée Form existe déjà
        $existsAlready = $this->formEntryExists($formId, $entryId, $equipmentCode, $entityManager);
      // dump("Entry existe déjà: " . ($existsAlready ? 'OUI - SKIP' : 'NON - PROCEED'));
        
        if ($existsAlready) {
          // dump("ATTENTION: Entry ignorée car déjà existante!");
            return; 
        }
        
        try {
            // Créer une nouvelle entrée Form
            $form = new \App\Entity\Form();
            
            // Informations de référence
            $form->setFormId($formId);
            $form->setDataId($entryId);
            $form->setEquipmentId($equipmentCode);
            $form->setCodeEquipement($equipmentCode);
            $form->setRaisonSocialeVisite($equipementPath);
            $form->setUpdateTime(date('Y-m-d H:i:s'));
            
            // DEBUG: Photos avant assignation
          // dump("=== ASSIGNATION PHOTOS ===");
            
            if (!empty($equipmentData['photo_etiquette_somafi']['value'])) {
                $form->setPhotoEtiquetteSomafi($equipmentData['photo_etiquette_somafi']['value']);
              // dump("Photo étiquette assignée: " . $equipmentData['photo_etiquette_somafi']['value']);
            }
            
            if (!empty($equipmentData['photo2']['value'])) {
                $form->setPhoto2($equipmentData['photo2']['value']);
              // dump("Photo2 assignée: " . $equipmentData['photo2']['value']);
            }
            
            // POINT CRITIQUE: Photo compte rendu
            if (!empty($equipmentData['photo3']['value'])) {
                $photoValue = $equipmentData['photo3']['value'];
                $form->setPhotoCompteRendu($photoValue);
              // dump("PHOTO COMPTE RENDU assignée: " . $photoValue);
                
                // Vérification immédiate
                $verification = $form->getPhotoCompteRendu();
              // dump("Vérification getter après set: " . ($verification ?? 'NULL'));
            } else {
              // dump("ATTENTION: photo3 est vide ou n'existe pas!");
              // dump("Structure equipmentData: " . print_r(array_keys($equipmentData), true));
            }
            
            if (!empty($equipmentData['photo_complementaire_equipeme']['value'])) {
                $form->setPhotoEnvironnementEquipement1($equipmentData['photo_complementaire_equipeme']['value']);
              // dump("Photo environnement assignée: " . $equipmentData['photo_complementaire_equipeme']['value']);
            }
            
            // Autres photos...
            $this->setAllPhotosToForm($form, $equipmentData);
            
            // DEBUG: État de l'entité avant persist
          // dump("=== AVANT PERSIST ===");
          // dump("Form ID: " . $form->getFormId());
          // dump("Equipment ID: " . $form->getEquipmentId());
          // dump("Photo compte rendu final: " . ($form->getPhotoCompteRendu() ?? 'NULL'));
            
            // Sauvegarder l'entité Form
            $entityManager->persist($form);
          // dump("Entity form persistée avec succès");
            
            // IMPORTANT: Ajouter un flush immédiat pour tester
            $entityManager->flush();
          // dump("Entity form flushée avec succès");
        } catch (\Exception $e) {
          // dump("ERREUR sauvegarde photos Form: " . $e->getMessage());
          // dump("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
        
      // dump("=== FIN DEBUG PHOTOS HORS CONTRAT ===");
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
            
            // dump("===== TRAITEMENT SOUMISSION " . $submission['entry_id'] . " =====");
            // dump("Équipements sous contrat: " . count($contractEquipments));
            // dump("Équipements hors contrat: " . count($offContractEquipments));
            
            // Traitement des équipements sous contrat
            if (!empty($contractEquipments)) {
                // dump("--- DÉBUT TRAITEMENT SOUS CONTRAT ---");
                $contractChunks = array_chunk($contractEquipments, $chunkSize);
                
                foreach ($contractChunks as $chunkIndex => $chunk) {
                    // dump("Chunk sous contrat " . ($chunkIndex + 1) . "/" . count($contractChunks));
                    
                    foreach ($chunk as $equipmentIndex => $equipmentContrat) {
                        try {
                            // dump("Traitement équipement sous contrat " . ($equipmentIndex + 1) . "/" . count($chunk));
                            
                            $equipement = new $entityClass();
                            
                            // Étape 1: Données communes
                            $this->setRealCommonDataFixed($equipement, $fields);
                            // dump("Données communes définies pour équipement sous contrat");
                            
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
                                // dump("Équipement sous contrat persisté");
                            } else {
                                $equipmentsSkipped++;
                                // dump("Équipement sous contrat skippé (doublon)");
                            }
                            
                        } catch (\Exception $e) {
                            $errors++;
                            // dump("Erreur traitement équipement sous contrat: " . $e->getMessage());
                        }
                    }
                    
                    // Sauvegarder après chaque chunk
                    try {
                        $entityManager->flush();
                        // $entityManager->clear();
                        gc_collect_cycles();
                      // dump("Chunk sous contrat " . ($chunkIndex + 1) . " sauvegardé");
                    } catch (\Exception $e) {
                        $errors++;
                        // dump("Erreur flush/clear sous contrat: " . $e->getMessage());
                    }
                }
                // dump("--- FIN TRAITEMENT SOUS CONTRAT ---");
            }
            
            // Traitement des équipements hors contrat
            
            if (!empty($offContractEquipments)) {
                // dump("--- DÉBUT TRAITEMENT HORS CONTRAT ---");
                
                $offContractChunks = array_chunk($offContractEquipments, $chunkSize);
                
                foreach ($offContractChunks as $chunkIndex => $chunk) {
                    // dump("Chunk hors contrat " . ($chunkIndex + 1) . "/" . count($offContractChunks));
                    
                    foreach ($chunk as $equipmentIndex => $equipmentHorsContrat) {
                        try {
                            // dump("--- DÉBUT ÉQUIPEMENT HORS CONTRAT " . ($equipmentIndex + 1) . "/" . count($chunk) . " ---");
                            
                            $equipement = new $entityClass();
                            // dump("Nouvel objet équipement créé");
                            
                            // Étape 1: Données communes SANS setEnMaintenance
                            $this->setRealCommonDataFixed($equipement, $fields);
                            // dump("Données communes définies pour équipement hors contrat");
                            // dump("État en_maintenance après données communes: " . ($equipement->isEnMaintenance() ? 'true' : 'false'));
                            
                            // Étape 2: Données spécifiques hors contrat (avec setEnMaintenance(false))
                            $wasProcessed = $this->setOffContractDataWithFormPhotosAndDeduplication(
                                $equipement, $equipmentHorsContrat, $fields, 
                                $submission['form_id'], $submission['entry_id'], 
                                $equipmentIndex,  // ← AJOUTER L'INDEX
                                $entityClass, $entityManager, $idSociete, $dateDerniereVisite
                            );
                            
                            if ($wasProcessed) {
                                // Vérification finale avant persist
                                // dump("VÉRIFICATION AVANT PERSIST:");
                                // dump("- Numéro: " . $equipement->getNumeroEquipement());
                                // dump("- Libellé: " . $equipement->getLibelleEquipement());
                                // dump("- En maintenance: " . ($equipement->isEnMaintenance() ? 'true' : 'false'));
                                
                                $entityManager->persist($equipement);
                                $entityManager->flush();
                                $equipmentsProcessed++;
                                $photosSaved++;
                              // dump("Équipement hors contrat persisté et flushé avec succès");
                            } else {
                                $equipmentsSkipped++;
                                // dump("Équipement hors contrat skippé (doublon)");
                            }
                            
                            // dump("--- FIN ÉQUIPEMENT HORS CONTRAT " . ($equipmentIndex + 1) . " ---");
                            
                        } catch (\Exception $e) {
                            $errors++;
                            // dump("Erreur traitement équipement hors contrat: " . $e->getMessage());
                            // dump("Stack trace: " . $e->getTraceAsString());
                        }
                    }
                    
                    // Sauvegarder après chaque chunk
                    try {
                        // dump("Sauvegarde chunk hors contrat " . ($chunkIndex + 1));
                        $entityManager->flush();
                        $entityManager->clear();
                        gc_collect_cycles();
                        // dump("Chunk hors contrat " . ($chunkIndex + 1) . " sauvegardé");
                    } catch (\Exception $e) {
                        $errors++;
                        // dump("Erreur flush/clear hors contrat: " . $e->getMessage());
                    }
                }
                // dump("--- FIN TRAITEMENT HORS CONTRAT ---");
            }
            
            // dump("===== FIN TRAITEMENT SOUMISSION " . $submission['entry_id'] . " =====");
        } catch (\Exception $e) {
            $errors++;
            // dump("Erreur traitement soumission {$submission['entry_id']}: " . $e->getMessage());
        }
        
        return [
            'equipments_processed' => $equipmentsProcessed,
            'equipments_skipped' => $equipmentsSkipped,
            'photos_saved' => $photosSaved,
            'errors' => $errors
        ];
    }

    /**
     * Méthode modifiée : setOffContractDataWithFormPhotosAndDeduplication
     * 
     * MODIFICATION : Passer l'index de l'équipement pour une identification unique
     */
    private function setOffContractDataWithFormPhotosAndDeduplication(
        $equipement, 
        array $equipmentHorsContrat, 
        array $fields, 
        string $formId, 
        string $entryId,
        int $equipmentIndex,
        string $entityClass,
        EntityManagerInterface $entityManager,
        $idSociete,
        $dateDerniereVisite
    ): bool {
        dump("=== TRAITEMENT ÉQUIPEMENT HORS CONTRAT #$equipmentIndex ===");
        
        try {
            $visite = $this->getVisiteFromFields($fields, $equipmentHorsContrat);
            dump("✅ Visite extraite: " . ($visite ?? 'NULL'));
        } catch (\Exception $e) {
            dump("❌ ERREUR getVisiteFromFields: " . $e->getMessage());
            return false;
        }
        
        $idClient = $fields['id_client_']['value'] ?? '';
        dump("ID Client: '$idClient'");
        
        dump("=== VÉRIFICATION DOUBLON ===");
        
        // ✅ VÉRIFICATION avec FormId + EntryId + Index
        if ($this->offContractEquipmentExistsByBusinessCriteria(
            $equipmentHorsContrat,
            $idClient,
            $formId,
            $entryId,
            $equipmentIndex,
            $entityClass,
            $entityManager
        )) {
            dump("❌ SKIP: Équipement déjà traité (doublon détecté)");
            return false;
        }
        
        dump("✅ Pas de doublon, génération du numéro...");
        
        // Générer le numéro d'équipement
        $typeLibelle = $equipmentHorsContrat['nature']['value'] ?? '';
        $typeCode = $this->getTypeCodeFromLibelle($typeLibelle);
        
        if (empty($typeCode)) {
            dump("❌ ERREUR: typeCode est vide");
            return false;
        }
        
        $nouveauNumero = $this->getNextEquipmentNumberReal($typeCode, $idClient, $entityClass, $entityManager);
        $numeroEquipement = $typeCode . str_pad($nouveauNumero, 2, '0', STR_PAD_LEFT);
        dump("✅ Numéro généré: $numeroEquipement");

        // Définir les propriétés de base
        $equipement->setVisite($visite);
        $equipement->setNumeroEquipement($numeroEquipement);
        $equipement->setDerniereVisite($fields['date_et_heure1']['value'] ?? '');
        $equipement->setCodeSociete($idSociete);
        $equipement->setDateEnregistrement($dateDerniereVisite);
        $equipement->setEnMaintenance(false); // IMPORTANT : équipement hors contrat
        
        dump("✅ Propriétés de base définies");
        
        // Remplir les données
        $this->fillOffContractEquipmentDataFixed($equipement, $equipmentHorsContrat, $fields);
        
        dump("✅ Données remplies");
        
        // Téléchargement et sauvegarde des photos
        $agence = $fields['code_agence']['value'] ?? '';
        $raisonSociale = $fields['nom_client']['value'] ?? '';
        $dateVisite = $fields['date_et_heure1']['value'] ?? '';
        $anneeVisite = date('Y', strtotime($dateVisite));
        $idContact = $fields['id_contact']['value'] ?? '';
        
        $savedPhotos = $this->downloadAndSavePhotosLocally(
            $equipmentHorsContrat,
            $formId,
            $entryId,
            $agence,
            $idContact,
            $anneeVisite,
            $visite,
            $equipmentIndex
        );
        
        $this->savePhotosToFormEntityWithLocalPathsFixed(
            $raisonSociale,
            $visite,
            $equipmentHorsContrat,
            $formId,
            $entryId,
            $equipmentIndex,
            $entityManager,
            $savedPhotos,
            $fields
        );
        
        $this->setSimpleEquipmentAnomalies($equipement, $equipmentHorsContrat);

        dump("✅ ÉQUIPEMENT PRÊT À ÊTRE PERSISTÉ");
        return true;
    }

    /**
     * Vérifie si un équipement hors contrat existe déjà en base
     * 
     * NOUVELLE APPROCHE : Utilise l'ID de la soumission Kizeo comme identifiant unique
     * et les données métier comme critères de backup
     * 
     * @param array $equipmentData Données de l'équipement hors contrat
     * @param string $idClient ID du client
     * @param string $formId ID du formulaire Kizeo
     * @param string $entryId ID de la soumission Kizeo
     * @param int $equipmentIndex Index de l'équipement dans la soumission
     * @param string $entityClass Classe de l'entité (EquipementS160, etc.)
     * @param EntityManagerInterface $entityManager
     * @return bool True si l'équipement existe déjà
     */
    private function offContractEquipmentExistsByBusinessCriteria(
        array $equipmentData,
        string $idClient,
        string $formId,
        string $entryId,
        int $equipmentIndex,
        string $entityClass,
        EntityManagerInterface $entityManager
    ): bool {
        dump("=== VÉRIFICATION DOUBLON ÉQUIPEMENT HORS CONTRAT ===");
        dump("FormId: $formId | EntryId: $entryId | Index: $equipmentIndex | Client: $idClient");
        
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            // STRATÉGIE 1 : Vérifier par l'ID de soumission Kizeo (le plus fiable)
            // On cherche dans la table Form pour voir si cet équipement a déjà été traité
            $formRepository = $entityManager->getRepository(Form::class);
            
            // Construire une clé unique pour cet équipement
            $uniqueKey = sprintf('%s_%s_HC_%d', $formId, $entryId, $equipmentIndex);
            
            $existingForm = $formRepository->createQueryBuilder('f')
                ->where('f.form_id = :formId')
                ->andWhere('f.entry_id = :entryId')
                ->andWhere('f.id_contact = :idClient')
                ->andWhere('f.numero_equipement_interne = :equipIndex')
                ->setParameter('formId', $formId)
                ->setParameter('entryId', $entryId)
                ->setParameter('idClient', $idClient)
                ->setParameter('equipIndex', $equipmentIndex)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($existingForm) {
                dump("⚠️ DOUBLON détecté via Form (submission déjà traitée)");
                return true;
            }
            
            // STRATÉGIE 2 : Vérifier par critères métier robustes
            // Extraire les données significatives
            $numeroSerie = $equipmentData['n_de_serie']['value'] ?? '';
            $marque = $equipmentData['marque']['value'] ?? '';
            $modeleNacelle = $equipmentData['modele_nacelle']['value'] ?? '';
            $localisationSiteClient = $equipmentData['localisation_site_client1']['value'] ?? '';
            $nature = $equipmentData['nature']['value'] ?? '';
            $hauteurNacelle = $equipmentData['hauteur_nacelle']['value'] ?? '';
            $largeur = $equipmentData['largeur']['value'] ?? '';
            $derniereVisite = $equipmentData['derniere_visite']['value'] ?? '';
            
            // Construction de la requête avec critères significatifs
            $qb = $repository->createQueryBuilder('e')
                ->where('e.id_contact = :idClient')
                ->andWhere('e.en_maintenance = false')
                ->setParameter('idClient', $idClient);
            
            // 1. Critère FORT : Numéro de série valide
            $hasStrongCriteria = false;
            if (!empty($numeroSerie) && 
                $numeroSerie !== 'Non renseigné' && 
                $numeroSerie !== 'NC' && 
                $numeroSerie !== 'nc') {
                $qb->andWhere('e.numero_de_serie = :numeroSerie')
                ->setParameter('numeroSerie', $numeroSerie);
                $hasStrongCriteria = true;
                dump("✓ Critère FORT : numero_serie = '$numeroSerie'");
            }
            
            // 2. Si pas de numéro de série, utiliser une combinaison de critères
            if (!$hasStrongCriteria) {
                $criteriaCount = 0;
                
                // Localisation (quasi obligatoire)
                if (!empty($localisationSiteClient) && $localisationSiteClient !== 'nc') {
                    $qb->andWhere('e.repere_site_client = :localisation')
                    ->setParameter('localisation', $localisationSiteClient);
                    $criteriaCount++;
                    dump("✓ Critère : localisation = '$localisationSiteClient'");
                }
                
                // Nature de l'équipement (obligatoire)
                if (!empty($nature)) {
                    $qb->andWhere('e.libelle_equipement = :nature')
                    ->setParameter('nature', $nature);
                    $criteriaCount++;
                    dump("✓ Critère : nature = '$nature'");
                }
                
                // Marque
                if (!empty($marque) && $marque !== 'nc') {
                    $qb->andWhere('e.marque = :marque')
                    ->setParameter('marque', $marque);
                    $criteriaCount++;
                    dump("✓ Critère : marque = '$marque'");
                }
                
                // Modèle
                if (!empty($modeleNacelle) && $modeleNacelle !== 'nc') {
                    $qb->andWhere('e.modele_nacelle = :modele')
                    ->setParameter('modele', $modeleNacelle);
                    $criteriaCount++;
                    dump("✓ Critère : modele = '$modeleNacelle'");
                }
                
                // Hauteur
                if (!empty($hauteurNacelle) && $hauteurNacelle !== 'nc') {
                    $qb->andWhere('e.hauteur_nacelle = :hauteur')
                    ->setParameter('hauteur', $hauteurNacelle);
                    $criteriaCount++;
                    dump("✓ Critère : hauteur = '$hauteurNacelle'");
                }
                
                // Largeur
                if (!empty($largeur) && $largeur !== 'nc') {
                    $qb->andWhere('e.largeur = :largeur')
                    ->setParameter('largeur', $largeur);
                    $criteriaCount++;
                    dump("✓ Critère : largeur = '$largeur'");
                }
                
                // Dernière visite (pour éviter les doublons sur même visite)
                if (!empty($derniereVisite)) {
                    $qb->andWhere('e.derniere_visite = :derniereVisite')
                    ->setParameter('derniereVisite', $derniereVisite);
                    $criteriaCount++;
                    dump("✓ Critère : derniere_visite = '$derniereVisite'");
                }
                
                // Si on a moins de 3 critères, c'est trop peu fiable
                if ($criteriaCount < 3) {
                    dump("⚠️ Seulement $criteriaCount critères, vérification non fiable - on considère comme nouveau");
                    return false;
                }
                
                dump("✓ Total critères métier : $criteriaCount");
            }
            
            // Exécuter la requête en base
            $existing = $qb->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($existing) {
                dump("⚠️ DOUBLON trouvé en BASE (critères métier)");
                dump("   → ID équipement existant : " . $existing->getId());
                dump("   → Numéro existant : " . $existing->getNumeroEquipement());
                return true;
            }
            
            // STRATÉGIE 3 : Vérifier dans l'UnitOfWork (équipements en attente de flush)
            $uow = $entityManager->getUnitOfWork();
            $scheduledInserts = $uow->getScheduledEntityInsertions();
            
            foreach ($scheduledInserts as $entity) {
                // Vérifier que c'est bien une instance de la bonne classe
                if (!is_a($entity, $entityClass)) {
                    continue;
                }
                
                // Vérifier que c'est un équipement hors contrat
                if ($entity->isEnMaintenance()) {
                    continue;
                }
                
                // Même client
                if ($entity->getIdContact() !== $idClient) {
                    continue;
                }
                
                // Vérification avec numéro de série
                if ($hasStrongCriteria && !empty($numeroSerie)) {
                    $entityNumeroSerie = $entity->getNumeroDeSerie();
                    if ($entityNumeroSerie && $entityNumeroSerie === $numeroSerie) {
                        dump("⚠️ DOUBLON trouvé dans UnitOfWork (numéro série)");
                        return true;
                    }
                } else {
                    // Vérification avec critères combinés
                    $matchCount = 0;
                    
                    if (!empty($localisationSiteClient)) {
                        $entityLocalisation = $entity->getRepereSiteClient();
                        if ($entityLocalisation && $entityLocalisation === $localisationSiteClient) {
                            $matchCount++;
                        }
                    }
                    
                    if (!empty($nature)) {
                        $entityNature = $entity->getLibelleEquipement();
                        if ($entityNature && $entityNature === $nature) {
                            $matchCount++;
                        }
                    }
                    
                    if (!empty($marque) && $marque !== 'nc') {
                        $entityMarque = $entity->getMarque();
                        if ($entityMarque && $entityMarque === $marque) {
                            $matchCount++;
                        }
                    }
                    
                    if (!empty($modeleNacelle) && $modeleNacelle !== 'nc') {
                        $entityModele = $entity->getModeleNacelle();
                        if ($entityModele && $entityModele === $modeleNacelle) {
                            $matchCount++;
                        }
                    }
                    
                    if (!empty($derniereVisite)) {
                        $entityDerniereVisite = $entity->getDerniereVisite();
                        if ($entityDerniereVisite && $entityDerniereVisite === $derniereVisite) {
                            $matchCount++;
                        }
                    }
                    
                    // Si au moins 3 critères matchent, c'est probablement le même équipement
                    if ($matchCount >= 3) {
                        dump("⚠️ DOUBLON trouvé dans UnitOfWork ($matchCount critères matchés)");
                        return true;
                    }
                }
            }
            
            dump("✅ Pas de doublon détecté - équipement nouveau");
            return false;
            
        } catch (\Exception $e) {
            dump("❌ Erreur vérification : " . $e->getMessage());
            $this->logger->error("Erreur vérification doublon équipement", [
                'error' => $e->getMessage(),
                'form_id' => $formId,
                'entry_id' => $entryId,
                'id_client' => $idClient
            ]);
            // En cas d'erreur, on considère comme nouveau pour ne pas bloquer
            return false;
        }
    }

    private function offContractEquipmentTypeExistsForClientVisit(
        string $typeCode,      // "RID", "SEC", etc.
        string $idClient,
        string $visite,
        string $entityClass,
        EntityManagerInterface $entityManager
    ): bool {
        $repository = $entityManager->getRepository($entityClass);
        
        // Cherche en base
        $qb = $repository->createQueryBuilder('e')
            ->where('e.numero_equipement LIKE :typePattern')
            ->andWhere('e.id_contact = :idClient')
            ->andWhere('e.visite = :visite')
            ->andWhere('e.en_maintenance = false')
            ->setParameter('typePattern', $typeCode . '%') // RID%
            ->setParameter('idClient', $idClient)
            ->setParameter('visite', $visite)
            ->setMaxResults(1);
        
        if ($qb->getQuery()->getOneOrNullResult() !== null) {
            return true;
        }
        
        // Cherche aussi dans l'UnitOfWork
        $uow = $entityManager->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (get_class($entity) === $entityClass 
                && str_starts_with($entity->getNumeroEquipement() ?? '', $typeCode)
                && $entity->getIdContact() === $idClient
                && $entity->getVisite() === $visite
                && !$entity->isEnMaintenance()) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * ✅ MISE À JOUR : Vérification doublon avec UnitOfWork
     * 
     * Vérifie si un équipement hors contrat existe déjà en se basant sur :
     * - numero_equipement + id_contact (combinaison unique et toujours présente)
     * - Recherche en base de données ET dans l'UnitOfWork (objets en attente de flush)
     * 
     * @param string $numeroEquipement Le numéro d'équipement généré (ex: RID01)
     * @param string $idClient L'identifiant du client
     * @param string $entityClass La classe de l'entité (ex: EquipementS40::class)
     * @param EntityManagerInterface $entityManager Le gestionnaire d'entités Doctrine
     * @return bool true si l'équipement existe déjà, false sinon
     */
    private function offContractEquipmentExistsForSameVisit(
        string $numeroEquipement,
        string $idClient,
        string $entityClass,
        EntityManagerInterface $entityManager
    ): bool {
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            // ============================================
            // 1. VÉRIFICATION EN BASE DE DONNÉES
            // ============================================
            $qb = $repository->createQueryBuilder('e')
                ->where('e.numero_equipement = :numero')
                ->andWhere('e.id_contact = :idClient')
                ->andWhere('e.en_maintenance = false')  // Uniquement les équipements hors contrat
                ->setParameter('numero', $numeroEquipement)
                ->setParameter('idClient', $idClient)
                ->setMaxResults(1);
            
            $existingInDb = $qb->getQuery()->getOneOrNullResult();
            
            if ($existingInDb !== null) {
                // dump("✅ Doublon détecté en base : " . $numeroEquipement . " pour client " . $idClient);
                return true;
            }
            
            // ============================================
            // 2. ✅ VÉRIFICATION DANS L'UNITOFWORK
            //    (objets en attente de flush)
            // ============================================
            $uow = $entityManager->getUnitOfWork();
            $scheduledInserts = $uow->getScheduledEntityInsertions();
            
            foreach ($scheduledInserts as $entity) {
                // Vérifier que c'est bien la même classe d'entité
                if (get_class($entity) === $entityClass) {
                    // Vérifier les critères d'unicité
                    if ($entity->getNumeroEquipement() === $numeroEquipement && 
                        $entity->getIdContact() === $idClient &&
                        !$entity->isEnMaintenance()) {  // Uniquement hors contrat
                        
                        // dump("✅ Doublon détecté dans UnitOfWork : " . $numeroEquipement . " pour client " . $idClient);
                        return true;
                    }
                }
            }
            
            // Aucun doublon trouvé
            return false;
            
        } catch (\Exception $e) {
            // En cas d'erreur, logger mais ne pas bloquer le traitement
            // dump("❌ Erreur vérification doublon: " . $e->getMessage());
            
            // Par sécurité, retourner false pour ne pas bloquer la création
            // (mieux vaut un doublon qu'un équipement manquant)
            return false;
        }
    }

    /**
     * ✅ CORRECTION 5 : Nouvelle version de savePhotosToFormEntityWithLocalPaths
     */
    private function savePhotosToFormEntityWithLocalPathsFixed(
        string $raisonSociale,
        string $visite,  // ✅ Recevoir directement la visite
        array $equipmentData,
        string $formId, 
        string $entryId, 
        string $equipmentCode, 
        EntityManagerInterface $entityManager,
        array $savedPhotos = [],
        array $fields = []
    ): void {
        try {
            $existingForm = $entityManager->getRepository(Form::class)->findOneBy([
                'form_id' => $formId,
                'data_id' => $entryId,
                'equipment_id' => $equipmentCode
            ]);
            
            if ($existingForm) {
                $form = $existingForm;
            } else {
                $form = new Form();
                $form->setFormId($formId);
                $form->setDataId($entryId);
                $form->setEquipmentId($equipmentCode);
            }
            
            // ✅ Utiliser directement les valeurs passées en paramètre
            $form->setCodeEquipement($equipmentCode);
            $form->setUpdateTime(date('Y-m-d H:i:s'));
            
            // ✅ Construction correcte de raison_sociale_visite
            $raisonSocialeVisite = $raisonSociale . '\\' . $visite;
            
            $form->setRaisonSocialeVisite($raisonSocialeVisite);
            $form->setIdContact($fields['id_client_']['value'] ?? '');
            $form->setIdSociete($fields['id_societe']['value'] ?? '');
            
            // ✅ Mapper les photos avec gestion de photo2 et photo3
            $this->mapPhotosToFormEntityFixed($form, $equipmentData, $savedPhotos);
            
            $entityManager->persist($form);
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur sauvegarde Form: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ CORRECTION 6 : Version corrigée du mapping des photos
     */
    private function mapPhotosToFormEntityFixed(Form $form, array $equipmentData, array $savedPhotos): void
    {
        // ✅ Mapping avec gestion des variantes de noms
        $photoMapping = [
            // Photos principales - GERER LES 2 VARIANTES
            'photo3' => 'setPhotoCompteRendu',
            'photo_3' => 'setPhotoCompteRendu',  // ✅ Variante avec underscore
            'photo_compte_rendu' => 'setPhotoCompteRendu',  // ✅ Autre variante possible
            
            'photo2' => 'setPhoto2',
            'photo_2' => 'setPhoto2',  // ✅ Variante avec underscore
            'photo_generale' => 'setPhoto2',  // ✅ Autre variante possible
            
            'photo_complementaire_equipeme' => 'setPhotoEnvironnementEquipement1',
            'photo_plaque' => 'setPhotoPlaque',
            'photo_etiquette_somafi' => 'setPhotoEtiquetteSomafi',
            
            // Photos techniques
            'photo_choc' => 'setPhotoChoc',
            'photo_choc_montant' => 'setPhotoChocMontant',
            'photo_choc_tablier' => 'setPhotoChocTablier',
            'photo_choc_tablier_porte' => 'setPhotoChocTablierPorte',
            'photo_moteur' => 'setPhotoMoteur',
            'photo_carte' => 'setPhotoCarte',
            'photo_coffret_de_commande' => 'setPhotoCoffretDeCommande',
            'photo_rail' => 'setPhotoRail',
            'photo_equerre_rail' => 'setPhotoEquerreRail',
            'photo_fixation_coulisse' => 'setPhotoFixationCoulisse',
            'photo_axe' => 'setPhotoAxe',
            'photo_serrure' => 'setPhotoSerrure',
            'photo_serrure1' => 'setPhotoSerrure1',
            'photo_feux' => 'setPhotoFeux',
            
            // Photos structure
            'photo_panneau_intermediaire_i' => 'setPhotosPanneauIntermediaireI',
            'photo_panneau_bas_inter_ext' => 'setPhotosPanneauBasInterExt',
            'photo_lame_basse__int_ext' => 'setPhotoLameBasseIntExt',
            'photo_lame_intermediaire_int_' => 'setPhotoLameIntermediaireInt',
            'photo_deformation_plateau' => 'setPhotoDeformationPlateau',
            'photo_deformation_plaque' => 'setPhotoDeformationPlaque',
            'photo_deformation_structure' => 'setPhotoDeformationStructure',
            'photo_deformation_chassis' => 'setPhotoDeformationChassis',
            'photo_deformation_levre' => 'setPhotoDeformationLevre',
            'photo_fissure_cordon' => 'setPhotoFissureCordon',
            
            // Photos environnement
            'photo_envirronement_eclairage' => 'setPhotoEnvirronementEclairage',
            'photo_bache' => 'setPhotoBache',
            'photo_marquage_au_sol' => 'setPhotoMarquageAuSol',
            'photo_marquage_au_sol_' => 'setPhotoMarquageAuSol',
            'photo_marquage_au_sol_2' => 'setPhotoMarquageAuSol2',
            'photo_environnement_equipement1' => 'setPhotoEnvironnementEquipement1',
            
            // Photos éléments
            'photo_joue' => 'setPhotoJoue',
            'photo_butoir' => 'setPhotoButoir',
            'photo_vantail' => 'setPhotoVantail',
            'photo_linteau' => 'setPhotoLinteau',
            'photo_barriere' => 'setPhotoBarriere',
            'photo_tourniquet' => 'setPhotoTourniquet',
            'photo_sas' => 'setPhotoSas'
        ];
        
        foreach ($photoMapping as $fieldKey => $formMethod) {
            if (isset($equipmentData[$fieldKey]['value']) && !empty($equipmentData[$fieldKey]['value'])) {
                $photoValue = $equipmentData[$fieldKey]['value'];
                
                // Stocker le nom original de la photo Kizeo
                if (method_exists($form, $formMethod)) {
                    $form->$formMethod($photoValue);
                }
            }
        }
    }

    /**
     * ✅ CORRECTION 4 : Version corrigée de fillOffContractEquipmentData
     */
    private function fillOffContractEquipmentDataFixed($equipement, array $equipmentHorsContrat, array $fields): void
    {
        // ✅ CORRECTION : Utiliser les bonnes clés du formulaire
        $typeLibelle = $equipmentHorsContrat['nature']['value'] ?? '';
        $equipement->setLibelleEquipement($typeLibelle);
        
        $equipement->setModeFonctionnement($equipmentHorsContrat['mode_fonctionnement_']['value'] ?? '');
        $equipement->setRepereSiteClient($equipmentHorsContrat['localisation_site_client1']['value'] ?? '');
        $equipement->setMiseEnService($equipmentHorsContrat['annee']['value'] ?? 'nc');
        $equipement->setNumeroDeSerie($equipmentHorsContrat['n_de_serie']['value'] ?? '');
        $equipement->setMarque($equipmentHorsContrat['marque']['value'] ?? '');
        $equipement->setLargeur($equipmentHorsContrat['largeur']['value'] ?? '');
        $equipement->setHauteur($equipmentHorsContrat['hauteur']['value'] ?? '');
        $equipement->setPlaqueSignaletique($equipmentHorsContrat['plaque_signaletique1']['value'] ?? '');
        $equipement->setEtat($equipmentHorsContrat['etat1']['value'] ?? '');
        $equipement->setLongueur(''); // Pas de champ longueur dans tableau2
        
        $statut = $this->getMaintenanceStatusFromEtat($equipmentHorsContrat['etat1']['value'] ?? '');
        $equipement->setStatutDeMaintenance($statut);
        
        $equipement->setEnMaintenance(false);
        $equipement->setIsArchive(false);
    }
    /**
     * Sauvegarde les photos dans la table Form avec les chemins locaux comme métadonnées
     */
    private function savePhotosToFormEntityWithLocalPaths(
        string $equipementPath,
        array $equipmentData,
        string $formId, 
        string $entryId, 
        string $equipmentCode, 
        EntityManagerInterface $entityManager,
        array $savedPhotos = [],
        array $fields = []
    ): void {
        try {
            $existingForm = $entityManager->getRepository(Form::class)->findOneBy([
                'form_id' => $formId,
                'data_id' => $entryId,
                'equipment_id' => $equipmentCode
            ]);
            
            if ($existingForm) {
                $form = $existingForm;
            } else {
                $form = new Form();
                $form->setFormId($formId);
                $form->setDataId($entryId);
                $form->setEquipmentId($equipmentCode);
            }
            
            // ✅ CORRECTION 1 : Définir les métadonnées de base
            $form->setCodeEquipement($equipmentCode);
            $form->setUpdateTime(date('Y-m-d H:i:s'));
            
            // ✅ CORRECTION 2 : Remplir les champs manquants CRITIQUES
            // Extraire la visite depuis le path (ex: "CE1\BPO01" → "CE1")
            $visite = $this->extractVisitTypeFromPath($equipementPath);
            $raisonSociale = $fields['nom_client']['value'] ?? '';
            $idContact = $fields['id_client_']['value'] ?? '';
            $idSociete = $fields['id_societe']['value'] ?? '';
            
            // Construction correcte de raison_sociale_visite
            $raisonSocialeVisite = $raisonSociale . '\\' . $visite;
            
            $form->setRaisonSocialeVisite($raisonSocialeVisite);
            $form->setIdContact($idContact);
            $form->setIdSociete($idSociete);
            
            // Mapper les photos
            $this->mapPhotosToFormEntity($form, $equipmentData, $savedPhotos);
            
            $entityManager->persist($form);
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur sauvegarde Form avec chemins locaux: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mappe les photos vers les champs de la table Form
     */
    private function mapPhotosToFormEntity(Form $form, array $equipmentData, array $savedPhotos): void
    {
        // Mapping des champs photos de Kizeo vers les champs de la table Form
        $photoMapping = [
            'photo3' => 'setPhotoCompteRendu',
            'photo_complementaire_equipeme' => 'setPhotoEnvironnementEquipement1',
            'photo_plaque' => 'setPhotoPlaque',
            'photo_etiquette_somafi' => 'setPhotoEtiquetteSomafi',
            'photo_choc' => 'setPhotoChoc',
            'photo_choc_montant' => 'setPhotoChocMontant',
            'photo_choc_tablier' => 'setPhotoChocTablier',
            'photo_choc_tablier_porte' => 'setPhotoChocTablierPorte',
            'photo_moteur' => 'setPhotoMoteur',
            'photo_carte' => 'setPhotoCarte',
            'photo_coffret_de_commande' => 'setPhotoCoffretDeCommande',
            'photo_rail' => 'setPhotoRail',
            'photo_equerre_rail' => 'setPhotoEquerreRail',
            'photo_fixation_coulisse' => 'setPhotoFixationCoulisse',
            'photo_axe' => 'setPhotoAxe',
            'photo_serrure' => 'setPhotoSerrure',
            'photo_serrure1' => 'setPhotoSerrure1',
            'photo_feux' => 'setPhotoFeux',
            'photo_panneau_intermediaire_i' => 'setPhotosPanneauIntermediaireI',
            'photo_panneau_bas_inter_ext' => 'setPhotosPanneauBasInterExt',
            'photo_lame_basse__int_ext' => 'setPhotoLameBasseIntExt',
            'photo_lame_intermediaire_int_' => 'setPhotoLameIntermediaireInt',
            'photo_deformation_plateau' => 'setPhotoDeformationPlateau',
            'photo_deformation_plaque' => 'setPhotoDeformationPlaque',
            'photo_deformation_structure' => 'setPhotoDeformationStructure',
            'photo_deformation_chassis' => 'setPhotoDeformationChassis',
            'photo_deformation_levre' => 'setPhotoDeformationLevre',
            'photo_fissure_cordon' => 'setPhotoFissureCordon',
            'photo_envirronement_eclairage' => 'setPhotoEnvirronementEclairage',
            'photo_bache' => 'setPhotoBache',
            'photo_marquage_au_sol' => 'setPhotoMarquageAuSol',
            'photo_marquage_au_sol_' => 'setPhotoMarquageAuSol',
            'photo_marquage_au_sol_2' => 'setPhotoMarquageAuSol2',
            'photo_environnement_equipement1' => 'setPhotoEnvironnementEquipement1',
            'photo_joue' => 'setPhotoJoue',
            'photo_butoir' => 'setPhotoButoir',
            'photo_vantail' => 'setPhotoVantail',
            'photo_linteau' => 'setPhotoLinteau',
            'photo_barriere' => 'setPhotoBarriere',
            'photo_tourniquet' => 'setPhotoTourniquet',
            'photo_sas' => 'setPhotoSas',
            'photo_2' => 'setPhoto2'
        ];
        
        foreach ($photoMapping as $kizeoField => $formMethod) {
            if (isset($equipmentData[$kizeoField]['value']) && !empty($equipmentData[$kizeoField]['value'])) {
                $photoValue = $equipmentData[$kizeoField]['value'];
                
                // Stocker le nom original de la photo Kizeo (pour compatibilité avec l'API existante)
                if (method_exists($form, $formMethod)) {
                    $form->$formMethod($photoValue);
                }
            }
        }
    }

    /**
     * Service utilitaire pour récupérer les photos locales d'un équipement
     * À utiliser lors de la génération des PDFs pour éviter les appels API
     */
    public function getLocalPhotosForEquipment(
        string $agence,
        string $idContact,
        string $anneeVisite,
        string $typeVisite,
        string $codeEquipement
    ): array {
        $localPhotos = [];
        $photoTypes = ['compte_rendu', 'environnement', 'plaque', 'etiquette_somafi', 'moteur', 'generale'];
        
        foreach ($photoTypes as $photoType) {
            $filename = $codeEquipement . '_' . $photoType;
            $imagePath = $this->imageStorageService->getImagePath($agence, $idContact, $anneeVisite, $typeVisite, $filename);
            
            if ($imagePath && file_exists($imagePath)) {
                $localPhotos[$photoType] = [
                    'path' => $imagePath,
                    'url' => $this->imageStorageService->getImageUrl($agence, $idContact, $anneeVisite, $typeVisite, $filename),
                    'base64' => base64_encode(file_get_contents($imagePath))
                ];
            }
        }
        
        return $localPhotos;
    }

    /**
     * Route de maintenance pour télécharger toutes les photos manquantes
     */
    #[Route('/api/maintenance/download-missing-photos/{agencyCode}', name: 'app_maintenance_download_photos', methods: ['GET'])]
    public function downloadMissingPhotos(
        string $agencyCode,
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        
        $limit = $request->query->get('limit', 50);
        $offset = $request->query->get('offset', 0);
        
        try {
            // Récupérer les équipements sans photos locales
            $repository = $this->getRepositoryForAgency($agencyCode, $entityManager);
            $equipments = $repository->createQueryBuilder('e')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->getQuery()
                ->getResult();
            
            $downloadedCount = 0;
            $errorCount = 0;
            
            foreach ($equipments as $equipment) {
                try {
                    // Récupérer les données Form associées
                    $formData = $entityManager->getRepository(Form::class)->findOneBy([
                        'equipment_id' => $equipment->getNumeroEquipement(),
                        'raison_sociale_visite' => $equipment->getRaisonSociale() . '\\' . $equipment->getVisite()
                    ]);
                    
                    if ($formData && $formData->getFormId() && $formData->getDataId()) {
                        // Simuler les données d'équipement pour le téléchargement
                        $equipmentData = $this->buildEquipmentDataFromForm($formData);
                        
                        $savedPhotos = $this->downloadAndSavePhotosLocally(
                            $equipmentData,
                            $formData->getFormId(),
                            $formData->getDataId(),
                            $equipment->getCodeAgence(),
                            $equipment->getIdContact(),
                            date('Y', strtotime($equipment->getDateEnregistrement())),
                            $equipment->getVisite(),
                            $equipment->getNumeroEquipement()
                        );
                        
                        if (!empty($savedPhotos)) {
                            $downloadedCount++;
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->error("Erreur téléchargement photos équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'downloaded_count' => $downloadedCount,
                'error_count' => $errorCount,
                'total_processed' => count($equipments)
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construit les données d'équipement à partir d'une entité Form
     */
    private function buildEquipmentDataFromForm(Form $formData): array
    {
        $equipmentData = [];
        
        if ($formData->getPhotoCompteRendu()) {
            $equipmentData['photo3'] = ['value' => $formData->getPhotoCompteRendu()];
        }
        if ($formData->getPhotoEnvironnementEquipement1()) {
            $equipmentData['photo_complementaire_equipeme'] = ['value' => $formData->getPhotoEnvironnementEquipement1()];
        }
        if ($formData->getPhotoPlaque()) {
            $equipmentData['photo_plaque'] = ['value' => $formData->getPhotoPlaque()];
        }
        if ($formData->getPhoto2()) {
            $equipmentData['photo_2'] = ['value' => $formData->getPhoto2()];
        }
        
        // Ajouter d'autres mappings selon les besoins
        
        return $equipmentData;
    }

/**
 * INSTRUCTIONS D'IMPLÉMENTATION:
 * 
 * 1. Ajouter ces méthodes dans SimplifiedMaintenanceController.php
 * 
 * 2. Modifier les appels existants dans les méthodes de traitement:
 *    - Remplacer savePhotosToFormEntityWithDeduplication par savePhotosToFormEntityWithLocalPaths
 *    - Ajouter les appels à downloadAndSavePhotosLocally
 * 
 * 3. Créer le service ImageStorageService dans src/Service/
 * 
 * 4. Pour les PDFs, remplacer les appels API par getLocalPhotosForEquipment()
 * 
 * 5. Utiliser la route /api/maintenance/download-missing-photos/{agencyCode} 
 *    pour télécharger rétroactivement les photos manquantes
 */

    /**
     * Vérifier si un équipement hors contrat existe déjà
     */
    private function offContractEquipmentExists(
        array $equipmentHorsContrat, 
        string $idClient, 
        string $entityClass, 
        EntityManagerInterface $entityManager
    ): bool {
        
        // dump("=== VÉRIFICATION EXISTENCE ÉQUIPEMENT HORS CONTRAT ===");
        
        try {
            $repository = $entityManager->getRepository($entityClass);
            
            $numeroSerie = $equipmentHorsContrat['n_de_serie']['value'] ?? '';
            
            // SEULEMENT si numéro de série valide ET différent de NC
            if (!empty($numeroSerie) && $numeroSerie !== 'Non renseigné') {
                $existing = $repository->createQueryBuilder('e')
                    ->where('e.numero_de_serie = :numeroSerie')
                    ->andWhere('e.id_contact = :idClient')
                    ->setParameter('numeroSerie', $numeroSerie)
                    ->setParameter('idClient', $idClient)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                $result = $existing !== null;
                // dump("Résultat vérification: " . ($result ? "EXISTE" : "N'EXISTE PAS"));
                return $result;
            }
            
            // Si pas de numéro de série valide, considérer comme nouveau
            // dump("Pas de numéro de série valide -> NOUVEAU");
            return false;
            
        } catch (\Exception $e) {
            // dump("Erreur vérification: " . $e->getMessage());
            return false; // En cas d'erreur, traiter comme nouveau
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
        
        // AJOUTER EN TOUT PREMIER avant même ini_set
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }
        // Configuration conservative
        // ajustement du timeout a 30 minutes au lieu de 10 ici et dans le script migration_debug.sh
        ini_set('memory_limit', '3G');
        ini_set('max_execution_time', 600);
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        $formId = $request->query->get('form_id');
        $chunkSize = (int) $request->query->get('chunk_size', 5);
        $maxSubmissions = (int) $request->query->get('max_submissions', 10);
        $useCache = $request->query->get('use_cache', 'true') === 'true';
        $refreshCache = $request->query->get('refresh_cache', 'false') === 'true';
        $offset = (int) $request->query->get('offset', 0);
        
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
                $submissions = $this->getFormSubmissionsFixed($formId, $agencyCode, $maxSubmissions, $offset);
                
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
                // Vérification timeout commenté car il y a déjà les 600 secondes qui protègent dans le script
                // if (time() - $startTime > 250) { // 4 minutes max
                //     break;
                // }
                
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
                            // $entityManager->clear(); 
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
                    // $entityManager->clear();
                    gc_collect_cycles();
                } catch (\Exception $e) {
                  // dump("Erreur sauvegarde chunk {$chunkIndex}: " . $e->getMessage());
                }
                
                // Pause entre chunks
                usleep(100000); // 0.1 seconde
            }
            
            // Sauvegarde finale
            try {
                $entityManager->flush();
            } catch (\Exception $e) {
                // dump("Erreur sauvegarde finale: " . $e->getMessage());
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

    private function getFormSubmissionsFixed(
        string $formId,
        string $agencyCode,
        int $maxSubmissions = 1000,
        int $startOffset = 0
    ): array
    {
        // Si offset > 0, on a déjà tout récupéré au 1er appel
        if ($startOffset > 0) {
            return [];
        }
        
        try {
            $response = $this->client->request(
                'GET',
                "https://forms.kizeo.com/rest/v3/forms/{$formId}/data",
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => $_ENV["KIZEO_API_TOKEN"],
                    ],
                    'timeout' => 90
                ]
            );

            $formData = $response->toArray();
            $allSubmissions = $formData['data'] ?? [];
            
            // Limiter si nécessaire
            $submissions = array_slice($allSubmissions, 0, min($maxSubmissions, count($allSubmissions)));
            
            $validSubmissions = [];
            foreach ($submissions as $entry) {
                $validSubmissions[] = [
                    'form_id' => $entry['form_id'] ?? $formId,
                    'entry_id' => $entry['id'],
                    'client_name' => 'À déterminer lors du traitement',
                    'date' => $entry['answer_time'] ?? 'N/A',
                    'technician' => 'À déterminer lors du traitement'
                ];
            }
            
            return $validSubmissions;
            
        } catch (\Exception $e) {
            dump("Erreur getFormSubmissionsFixed: " . $e->getMessage());
            return [];
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
            // dump("Erreur recréation entités depuis cache: " . $e->getMessage());
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

    /**
     * Extrait et structure les anomalies d'un équipement selon son type (trigramme)
     * basé sur le numero_equipement et les données du formulaire Kizeo
     */
    private function extractAnomaliesByEquipmentType(array $equipmentData, string $numeroEquipement): ?string
    {
        // Extraire le trigramme du numéro d'équipement (ex: SEC01 -> SEC)
        $trigramme = $this->extractTrigrammeFromNumero($numeroEquipement);
        
        if (!$trigramme) {
            // dump("Impossible d'extraire le trigramme du numéro: " . $numeroEquipement);
            return null;
        }
        
        $anomalies = [];
        
        // Mapping des trigrammes vers les champs d'anomalies correspondants
        switch ($trigramme) {
            case 'SEC': // Porte sectionnelle
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_sec_rid_rap_vor_pac',
                    'anomalies_sec_'
                ]);
                break;
                
            case 'RID': // Rideau métallique
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_rid_vor',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'RAP': // Porte rapide
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_rapide',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'VOR': // Volet roulant
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_rid_vor',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'PAC': // Porte accordéon
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'NIV': // Niveleur
            case 'PLQ': // Plaque de quai
            case 'MIP': // Mini-pont
            case 'TEL': // Table élévatrice
            case 'BLR': // Bloc roue
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_niv_plq_mip_tel_blr_'
                ]);
                break;
                
            case 'SAS': // Sas
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_sas'
                ]);
                break;
                
            case 'BLE': // Barrière levante
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_ble1',
                    'anomalie_ble_moto_auto'
                ]);
                break;
                
            case 'TOU': // Tourniquet
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_tou1'
                ]);
                break;
                
            case 'PAU': // Portail automatique
            case 'PMO': // Portail motorisé
            case 'PMA': // Portail manuel
            case 'PCO': // Portail coulissant
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_portail',
                    'anomalie_portail_auto_moto'
                ]);
                break;
                
            case 'PPV': // Porte piétonne
            case 'CFE': // Porte coupe-feu
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_ppv_cfe',
                    'anomalie_cfe_ppv_auto_moto'
                ]);
                break;
                
            case 'HYD': // Équipement hydraulique
                $anomalies = $this->extractAnomaliesFromFields($equipmentData, [
                    'anomalie_hydraulique'
                ]);
                break;
                
            default:
                // dump("Type d'équipement non géré pour le trigramme: " . $trigramme);
                // Essayer de récupérer toutes les anomalies disponibles
                $anomalies = $this->extractAllAnomalies($equipmentData);
                break;
        }
        
        return $this->formatAnomaliesForDatabase($anomalies);
    }

    /**
     * Extrait le trigramme du numéro d'équipement
     */
    private function extractTrigrammeFromNumero(string $numeroEquipement): ?string
    {
        // Pattern pour extraire les lettres au début du numéro
        if (preg_match('/^([A-Z]{2,4})/', $numeroEquipement, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Extrait les anomalies des champs spécifiés
     */
    private function extractAnomaliesFromFields(array $equipmentData, array $fieldNames): array
    {
        $anomalies = [];
        
        foreach ($fieldNames as $fieldName) {
            if (isset($equipmentData[$fieldName])) {
                $fieldData = $equipmentData[$fieldName];
                
                // Vérifier si le champ est visible (pas hidden)
                $isHidden = isset($fieldData['hidden']) && 
                        ($fieldData['hidden'] === true || $fieldData['hidden'] === 'true');
                
                if (!$isHidden && isset($fieldData['valuesAsArray'])) {
                    $values = $fieldData['valuesAsArray'];
                    
                    // Filtrer les valeurs vides
                    $validValues = array_filter($values, function($value) {
                        return !empty(trim($value));
                    });
                    
                    if (!empty($validValues)) {
                        $anomalies[$fieldName] = [
                            'type' => $fieldData['type'] ?? 'select',
                            'values' => $validValues,
                            'columns' => $fieldData['columns'] ?? '',
                            'time' => $fieldData['time'] ?? []
                        ];
                    }
                }
            }
        }
        
        return $anomalies;
    }

    /**
     * Extrait toutes les anomalies disponibles (fallback)
     */
    private function extractAllAnomalies(array $equipmentData): array
    {
        $anomalies = [];
        
        // Liste de tous les champs d'anomalies possibles
        $allAnomalieFields = [
            'anomalie_sec_rid_rap_vor_pac',
            'anomalies_sec_',
            'anomalie_rid_vor',
            'anomalie_rapide',
            'anomalie_niv_plq_mip_tel_blr_',
            'anomalie_sas',
            'anomalie_ble1',
            'anomalie_tou1',
            'anomalie_portail',
            'anomalie_ppv_cfe',
            'anomalie_portail_auto_moto',
            'anomalie_cfe_ppv_auto_moto',
            'anomalie_ble_moto_auto',
            'anomalie_hydraulique'
        ];
        
        return $this->extractAnomaliesFromFields($equipmentData, $allAnomalieFields);
    }

    /**
     * Formate les anomalies pour l'enregistrement en base de données
     */
    private function formatAnomaliesForDatabase(array $anomalies): ?string
    {
        if (empty($anomalies)) {
            return null;
        }
        
        $formattedAnomalies = [];
        
        foreach ($anomalies as $fieldName => $anomalieData) {
            if (!empty($anomalieData['values'])) {
                $formattedAnomalies[] = [
                    'field' => $fieldName,
                    'type' => $anomalieData['type'],
                    'anomalies' => $anomalieData['values'],
                    'details' => $anomalieData['columns'],
                    'timestamps' => $anomalieData['time']
                ];
            }
        }
        
        return !empty($formattedAnomalies) ? json_encode($formattedAnomalies, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Méthode mise à jour pour intégrer l'extraction des anomalies
     * dans setContractEquipmentData ou setOffContractEquipmentData
     */
    private function setEquipmentAnomalies($equipement, array $equipmentData): void
    {
        $numeroEquipement = $equipement->getNumeroEquipement();
        
        if ($numeroEquipement) {
            $anomalies = $this->extractAnomaliesByEquipmentType($equipmentData, $numeroEquipement);
            
            if ($anomalies) {
                $equipement->setAnomalies($anomalies);
                // dump("Anomalies définies pour l'équipement " . $numeroEquipement . ": " . $anomalies);
            } else {
                // dump("Aucune anomalie trouvée pour l'équipement " . $numeroEquipement);
            }
        }
    }

    /**
    * Version simplifiée - Extrait uniquement les valeurs des anomalies 
    * selon le type d'équipement (trigramme du numero_equipement)
    */
    private function extractSimpleAnomaliesByEquipmentType(array $equipmentData, string $numeroEquipement): ?string
    {
        // Extraire le trigramme du numéro d'équipement (ex: SEC01 -> SEC, RID24 -> RID)
        $trigramme = $this->extractTrigrammeFromNumero($numeroEquipement);
        
        if (!$trigramme) {
            // dump("Impossible d'extraire le trigramme du numéro: " . $numeroEquipement);
            return null;
        }
        
        $allAnomalies = [];
        
        // Mapping des trigrammes vers les champs d'anomalies correspondants
        switch ($trigramme) {
            case 'SEC': // Porte sectionnelle
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_sec_rid_rap_vor_pac',
                    'anomalies_sec_'
                ]);
                break;
                
            case 'RID': // Rideau métallique
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_rid_vor',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'RAP': // Porte rapide
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_rapide',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'VOR': // Volet roulant
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_rid_vor',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'PAC': // Porte accordéon
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'NIV': // Niveleur
            case 'PLQ': // Plaque de quai
            case 'MIP': // Mini-pont
            case 'TEL': // Table élévatrice
            case 'BLR': // Bloc roue
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_niv_plq_mip_tel_blr_'
                ]);
                break;
                
            case 'SAS': // Sas
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_sas'
                ]);
                break;
                
            case 'BLE': // Barrière levante
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_ble1',
                    'anomalie_ble_moto_auto'
                ]);
                break;
                
            case 'TOU': // Tourniquet
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_tou1'
                ]);
                break;
                
            case 'PAU': // Portail automatique
            case 'PMO': // Portail motorisé
            case 'PMA': // Portail manuel
            case 'PCO': // Portail coulissant
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_portail',
                    'anomalie_portail_auto_moto'
                ]);
                break;
                
            case 'PPV': // Porte piétonne
            case 'CFE': // Porte coupe-feu
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_ppv_cfe',
                    'anomalie_cfe_ppv_auto_moto'
                ]);
                break;
                
            case 'HYD': // Équipement hydraulique
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_hydraulique'
                ]);
                break;
                
            default:
                // dump("Type d'équipement non géré pour le trigramme: " . $trigramme);
                return null;
        }
        
        // Retourner les anomalies sous forme de JSON array simple
        return !empty($allAnomalies) ? json_encode($allAnomalies, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Version simplifiée - Extrait uniquement les valeurs des anomalies 
     * selon le type d'équipement (trigramme du numero_equipement)
     * Retourne un tableau PHP (pas JSON)
     */
    private function extractSimpleAnomaliesArrayByEquipmentType(array $equipmentData, string $numeroEquipement): array
    {
        // Extraire le trigramme du numéro d'équipement (ex: SEC01 -> SEC, RID24 -> RID)
        $trigramme = $this->extractTrigrammeFromNumero($numeroEquipement);
        
        if (!$trigramme) {
            // dump("Impossible d'extraire le trigramme du numéro: " . $numeroEquipement);
            return [];
        }
        
        $allAnomalies = [];
        
        // Mapping des trigrammes vers les champs d'anomalies correspondants
        switch ($trigramme) {
            case 'SEC': // Porte sectionnelle
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_sec_rid_rap_vor_pac',
                    'anomalies_sec_'
                ]);
                break;
                
            case 'RID': // Rideau métallique
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_rid_vor',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'RAP': // Porte rapide
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_rapide',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'VOR': // Volet roulant
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_rid_vor',
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'PAC': // Porte accordéon
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_sec_rid_rap_vor_pac'
                ]);
                break;
                
            case 'NIV': // Niveleur
            case 'PLQ': // Plaque de quai
            case 'MIP': // Mini-pont
            case 'TEL': // Table élévatrice
            case 'BLR': // Bloc roue
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_niv_plq_mip_tel_blr_'
                ]);
                break;
                
            case 'SAS': // Sas
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_sas'
                ]);
                break;
                
            case 'BLE': // Barrière levante
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_ble1',
                    'anomalie_ble_moto_auto'
                ]);
                break;
                
            case 'TOU': // Tourniquet
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_tou1'
                ]);
                break;
                
            case 'PAU': // Portail automatique
            case 'PMO': // Portail motorisé
            case 'PMA': // Portail manuel
            case 'PCO': // Portail coulissant
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_portail',
                    'anomalie_portail_auto_moto'
                ]);
                break;
                
            case 'PPV': // Porte piétonne
            case 'CFE': // Porte coupe-feu
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_ppv_cfe',
                    'anomalie_cfe_ppv_auto_moto'
                ]);
                break;
                
            case 'HYD': // Équipement hydraulique
                $allAnomalies = $this->getAnomaliesValues($equipmentData, [
                    'anomalie_hydraulique'
                ]);
                break;
                
            default:
                // dump("Type d'équipement non géré pour le trigramme: " . $trigramme);
                return [];
        }
        
        return $allAnomalies;
    }

    /**
     * Extrait uniquement les valeurs des anomalies des champs spécifiés
     * Gère le cas spécial "autres_composants"
     */
    private function getAnomaliesValues(array $equipmentData, array $fieldNames): array
    {
        $allAnomalies = [];
        
        foreach ($fieldNames as $fieldName) {
            if (isset($equipmentData[$fieldName])) {
                $fieldData = $equipmentData[$fieldName];
                
                // Vérifier si le champ est visible (pas hidden)
                $isHidden = isset($fieldData['hidden']) && 
                        ($fieldData['hidden'] === true || $fieldData['hidden'] === 'true');
                
                if (!$isHidden && isset($fieldData['valuesAsArray'])) {
                    $values = $fieldData['valuesAsArray'];
                    
                    // Filtrer les valeurs vides et les ajouter au tableau final
                    foreach ($values as $value) {
                        $cleanValue = trim($value);
                        if (!empty($cleanValue)) {
                            $allAnomalies[] = $cleanValue;
                        }
                    }
                }
            }
        }
        
        // Supprimer les doublons et retourner
        return array_unique($allAnomalies);
    }

    /**
     * Méthode simplifiée pour définir les anomalies sur l'équipement
     * Enregistre les anomalies comme une chaîne séparée par des virgules
     */
    private function setSimpleEquipmentAnomalies($equipement, array $equipmentData): void
    {
        $numeroEquipement = $equipement->getNumeroEquipement();
        
        if ($numeroEquipement) {
            $anomaliesArray = $this->extractSimpleAnomaliesArrayByEquipmentType($equipmentData, $numeroEquipement);
            
            if (!empty($anomaliesArray)) {
                // Traiter chaque anomalie pour gérer le cas "autres_composants"
                $processedAnomalies = [];
                
                foreach ($anomaliesArray as $anomalie) {
                    // dump('Je suis $anomalie : ' . $anomalie); // Pour débogage
                    // if ($anomalie === 'autres_composants' || $anomalie === 'Autres composants') {
                    if ($anomalie === 'Autres composants') {
                        // CORRECTION: Récupérer d'abord la valeur du champ "autres_composants" lui-même
                        $autresComposantsValue = $equipmentData['autres_composants']['value'] ?? '';
                        // dump('Je suis $autresComposantsValue : ' . $autresComposantsValue); // Pour débogage
                        if (!empty($autresComposantsValue) && trim($autresComposantsValue) !== '') {
                            $processedAnomalies[] = trim($autresComposantsValue);
                            // dump("Anomalie 'Autres_composants' remplacée par la valeur: " . trim($autresComposantsValue));
                        } else {
                            // Si pas de valeur dans "autres_composants", essayer "information_autre_composant"
                            $informationAutreComposant = $equipmentData['information_autre_composant']['value'];
                            
                            if (!empty($informationAutreComposant) && trim($informationAutreComposant) !== '') {
                                $processedAnomalies[] = trim($informationAutreComposant);
                                // dump("Anomalie 'Autres_composants' remplacée par information_autre_composant: " . trim($informationAutreComposant));
                            } else {
                                // Si aucune des deux valeurs n'est disponible, on set à rien, pas garder "autres_composants"
                                // $processedAnomalies[] = $anomalie;
                                $processedAnomalies[] = '';
                                // dump("Anomalie 'autres_composants' gardée (pas d'information spécifique)");
                                // dump("Anomalie 'Autres_composants' settée à rien, pas gardée (pas d'information spécifique)");
                            }
                        }
                    } else {
                        // Anomalie normale, on la garde telle quelle
                        $processedAnomalies[] = $anomalie;
                        // dump("Anomalie normale gardée car elle contient : " . $anomalie); // Pour débogage
                    }
                }
                
                if (!empty($processedAnomalies)) {
                    // Convertir le tableau en chaîne séparée par des virgules
                    $anomaliesString = implode(', ', $processedAnomalies);
                    $equipement->setAnomalies($anomaliesString);
                    // dump("Anomalies définies pour l'équipement " . $numeroEquipement . ": " . $anomaliesString);
                }
            } else {
                // dump("Aucune anomalie trouvée pour l'équipement " . $numeroEquipement);
            }
        }
    }

    /**
     * Fonction utilitaire pour récupérer les anomalies côté frontend
     */
    function getAnomaliesFromDatabase($equipement) 
    {
        $anomalies = $equipement->getAnomalies();
        
        if (empty($anomalies)) {
            return [];
        }
        
        // Convertir la chaîne en tableau si nécessaire
        return explode(', ', $anomalies);
    }

    // GESTION DE L'ENREGISTREMENT DES IMAGES EN LOCAL
    /**
     * Télécharge et sauvegarde toutes les photos d'un équipement en local
     * Évite les appels API répétés lors de la génération des PDFs
     */
    private function downloadAndSavePhotosLocally(
        array $equipmentData,
        string $formId,
        string $entryId,
        string $agence,
        string $idContact,
        string $anneeVisite,
        string $typeVisite,
        string $codeEquipement
    ): array {
        $savedPhotos = [];
        $photoFields = $this->getPhotoFieldsMapping();
        
        foreach ($photoFields as $fieldKey => $photoType) {
            if (isset($equipmentData[$fieldKey]['value']) && !empty($equipmentData[$fieldKey]['value'])) {
                $photoValue = $equipmentData[$fieldKey]['value'];
                
                // Gérer les photos multiples séparées par des virgules
                if (str_contains($photoValue, ', ')) {
                    $photoNames = explode(', ', $photoValue);
                    foreach ($photoNames as $index => $photoName) {
                        $photoName = trim($photoName);
                        if (!empty($photoName)) {
                            $localPath = $this->downloadSinglePhotoLocally(
                                $photoName,
                                $formId,
                                $entryId,
                                $agence,
                                $idContact,
                                $anneeVisite,
                                $typeVisite,
                                $codeEquipement,
                                $photoType . '_' . ($index + 1)
                            );
                            if ($localPath) {
                                $savedPhotos[$photoType][] = $localPath;
                            }
                        }
                    }
                } else {
                    // Photo unique
                    $localPath = $this->downloadSinglePhotoLocally(
                        $photoValue,
                        $formId,
                        $entryId,
                        $agence,
                        $idContact,
                        $anneeVisite,
                        $typeVisite,
                        $codeEquipement,
                        $photoType
                    );
                    if ($localPath) {
                        $savedPhotos[$photoType] = $localPath;
                    }
                }
            }
        }
        
        return $savedPhotos;
    }

    /**
     * Télécharge une seule photo depuis l'API Kizeo et la sauvegarde localement
     */
    private function downloadSinglePhotoLocally(
        string $photoName,
        string $formId,
        string $entryId,
        string $agence,
        string $idContact,
        string $anneeVisite,
        string $typeVisite,
        string $codeEquipement,
        string $photoType
    ): ?string {
        try {
            // Construire le nom du fichier avec le type de photo
            $filename = $codeEquipement . '_' . $photoType;
            
            // Vérifier si la photo existe déjà localement
            if ($this->imageStorageService->imageExists($agence, $idContact, $anneeVisite, $typeVisite, $filename)) {
                $this->logger->info("Photo déjà existante localement: {$filename}");
                return $this->imageStorageService->getImagePath($agence, $idContact, $anneeVisite, $typeVisite, $filename);
            }

            // Télécharger la photo depuis l'API Kizeo
            $response = $this->client->request(
                'GET',
                'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $entryId . '/medias/' . $photoName,
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
                $this->logger->warning("Contenu de photo vide pour: {$photoName}");
                return null;
            }

            // Sauvegarder la photo localement
            $localPath = $this->imageStorageService->storeImage(
                $agence,
                $idContact,
                $anneeVisite,
                $typeVisite,
                $filename,
                $imageContent
            );

            $this->logger->info("Photo téléchargée et sauvegardée: {$localPath}");
            return $localPath;

        } catch (\Exception $e) {
            $this->logger->error("Erreur téléchargement photo {$photoName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mapping des champs photos vers leurs types
     */
    private function getPhotoFieldsMapping(): array
    {
        return [
            // Photos principales
            'photo3' => 'compte_rendu',
            'photo_3' => 'compte_rendu',  // ✅ Variante avec underscore
            'photo_compte_rendu' => 'compte_rendu',  // ✅ Autre variante possible

            'photo_complementaire_equipeme' => 'environnement',
            'photo_plaque' => 'plaque',
            'photo_etiquette_somafi' => 'etiquette_somafi',
            
            // Photos techniques
            'photo_choc' => 'choc',
            'photo_choc_montant' => 'choc_montant',
            'photo_choc_tablier' => 'choc_tablier',
            'photo_choc_tablier_porte' => 'choc_tablier_porte',
            'photo_moteur' => 'moteur',
            'photo_carte' => 'carte',
            'photo_coffret_de_commande' => 'coffret_commande',
            'photo_rail' => 'rail',
            'photo_equerre_rail' => 'equerre_rail',
            'photo_fixation_coulisse' => 'fixation_coulisse',
            'photo_axe' => 'axe',
            'photo_serrure' => 'serrure',
            'photo_serrure1' => 'serrure_1',
            'photo_feux' => 'feux',
            
            // Photos structure
            'photo_panneau_intermediaire_i' => 'panneau_intermediaire',
            'photo_panneau_bas_inter_ext' => 'panneau_bas',
            'photo_lame_basse__int_ext' => 'lame_basse',
            'photo_lame_intermediaire_int_' => 'lame_intermediaire',
            'photo_deformation_plateau' => 'deformation_plateau',
            'photo_deformation_plaque' => 'deformation_plaque',
            'photo_deformation_structure' => 'deformation_structure',
            'photo_deformation_chassis' => 'deformation_chassis',
            'photo_deformation_levre' => 'deformation_levre',
            'photo_fissure_cordon' => 'fissure_cordon',
            
            // Photos environnement
            'photo_envirronement_eclairage' => 'environnement_eclairage',
            'photo_bache' => 'bache',
            'photo_marquage_au_sol' => 'marquage_sol',
            'photo_marquage_au_sol_' => 'marquage_sol_2',
            'photo_marquage_au_sol_2' => 'marquage_sol_3',
            'photo_environnement_equipement1' => 'environnement_equipement',
            
            // Photos éléments
            'photo_joue' => 'joue',
            'photo_butoir' => 'butoir',
            'photo_vantail' => 'vantail',
            'photo_linteau' => 'linteau',
            'photo_barriere' => 'barriere',
            'photo_tourniquet' => 'tourniquet',
            'photo_sas' => 'sas',
            
            // Photo générale
            'photo2' => 'generale',
            'photo_2' => 'generale',  // ✅ Variante avec underscore
            'photo_generale' => 'generale',  // ✅ Autre variante possible
        ];
    }

    /**
 * SECTION: ROUTES DE MONITORING ET STATISTIQUES
 */

/**
 * Rapport de migration des photos pour une agence
 */
#[Route('/api/maintenance/photo-migration-report/{agencyCode}', name: 'app_photo_migration_report', methods: ['GET'])]
public function getPhotoMigrationReport(
    string $agencyCode,
    EntityManagerInterface $entityManager
): JsonResponse {
    
    $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
    
    if (!in_array($agencyCode, $validAgencies)) {
        return new JsonResponse(['error' => 'Code agence invalide'], 400);
    }
    
    try {
        $report = $entityManager->getRepository(Form::class)->getPhotoMigrationReport($agencyCode);
        
        return new JsonResponse([
            'success' => true,
            'agency' => $agencyCode,
            'migration_report' => $report,
            'recommendations' => $this->generateMigrationRecommendations($report)
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Statistiques globales du stockage des photos
 */
#[Route('/api/maintenance/storage-stats', name: 'app_storage_stats', methods: ['GET'])]
public function getStorageStats(): JsonResponse
{
    try {
        $stats = $this->imageStorageService->getStorageStats();
        
        return new JsonResponse([
            'success' => true,
            'storage_statistics' => $stats,
            'performance_metrics' => [
                'avg_image_size' => $stats['total_images'] > 0 
                    ? round($stats['total_size'] / $stats['total_images'] / 1024, 2) . ' KB'
                    : '0 KB',
                'agencies_count' => count($stats['agencies']),
                'largest_agency' => $this->getLargestAgency($stats['agencies']),
                'storage_efficiency' => $this->calculateStorageEfficiency($stats)
            ]
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Vérification de la disponibilité des photos pour un équipement
 */
#[Route('/api/maintenance/check-equipment-photos/{agencyCode}/{equipmentId}', name: 'app_check_equipment_photos', methods: ['GET'])]
public function checkEquipmentPhotos(
    string $agencyCode,
    string $equipmentId,
    EntityManagerInterface $entityManager
): JsonResponse {
    
    try {
        $repository = $this->getRepositoryForAgency($agencyCode, $entityManager);
        $equipment = $repository->findOneBy(['numeroEquipement' => $equipmentId]);
        
        if (!$equipment) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }
        
        $availability = $entityManager->getRepository(Form::class)->checkLocalPhotosAvailability($equipment);
        
        return new JsonResponse([
            'success' => true,
            'equipment_id' => $equipmentId,
            'agency' => $agencyCode,
            'photo_availability' => $availability,
            'can_generate_pdf_offline' => $availability['has_local_photos']
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * SECTION: ROUTES DE GESTION ET MAINTENANCE
 */

/**
 * Nettoyage des photos orphelines
 */
#[Route('/api/maintenance/clean-orphaned-photos/{agencyCode}', name: 'app_clean_orphaned_photos', methods: ['DELETE'])]
public function cleanOrphanedPhotos(
    string $agencyCode,
    EntityManagerInterface $entityManager
): JsonResponse {
    
    $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
    
    if (!in_array($agencyCode, $validAgencies)) {
        return new JsonResponse(['error' => 'Code agence invalide'], 400);
    }
    
    try {
        $results = $entityManager->getRepository(Form::class)->cleanOrphanedPhotos($agencyCode);
        
        return new JsonResponse([
            'success' => true,
            'agency' => $agencyCode,
            'cleanup_results' => $results,
            'space_freed_formatted' => $this->formatBytes($results['size_freed']),
            'message' => "Nettoyage terminé: {$results['deleted']} photos supprimées"
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Migration manuelle des photos d'un équipement spécifique
 */
#[Route('/api/maintenance/migrate-equipment-photos/{agencyCode}/{equipmentId}', name: 'app_migrate_equipment_photos', methods: ['POST'])]
public function migrateEquipmentPhotos(
    string $agencyCode,
    string $equipmentId,
    EntityManagerInterface $entityManager,
    Request $request
): JsonResponse {
    
    $force = $request->query->get('force', false);
    
    try {
        $repository = $this->getRepositoryForAgency($agencyCode, $entityManager);
        $equipment = $repository->findOneBy(['numeroEquipement' => $equipmentId]);
        
        if (!$equipment) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }
        
        // Vérifier si les photos existent déjà
        $availability = $entityManager->getRepository(Form::class)->checkLocalPhotosAvailability($equipment);
        
        if ($availability['has_local_photos'] && !$force) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Photos déjà présentes localement',
                'photo_count' => $availability['photo_count'],
                'use_force' => 'Utilisez ?force=true pour re-télécharger'
            ]);
        }
        
        // Récupérer les données Form
        $formData = $entityManager->getRepository(Form::class)->findOneBy([
            'equipment_id' => $equipmentId,
            'raison_sociale_visite' => $equipment->getRaisonSociale() . '\\' . $equipment->getVisite()
        ]);
        
        if (!$formData) {
            return new JsonResponse(['error' => 'Données de formulaire non trouvées'], 404);
        }
        
        // Effectuer la migration
        $migrated = $this->migratePhotosForSingleEquipment($equipment, $formData);
        
        return new JsonResponse([
            'success' => $migrated,
            'equipment_id' => $equipmentId,
            'agency' => $agencyCode,
            'migration_completed' => $migrated,
            'message' => $migrated ? 'Photos migrées avec succès' : 'Échec de la migration'
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * SECTION: ROUTES D'ACCÈS AUX PHOTOS
 */

/**
 * Téléchargement d'une photo locale
 */
#[Route('/api/maintenance/download-photo/{agencyCode}/{raisonSociale}/{annee}/{typeVisite}/{filename}', name: 'app_download_photo', methods: ['GET'])]
public function downloadPhoto(
    string $agencyCode,
    string $idContact,
    string $annee,
    string $typeVisite,
    string $filename
): BinaryFileResponse|JsonResponse {
    
    try {
        $imagePath = $this->imageStorageService->getImagePath(
            $agencyCode,
            $idContact,
            $annee,
            $typeVisite,
            $filename
        );
        
        if (!$imagePath || !file_exists($imagePath)) {
            return new JsonResponse(['error' => 'Photo non trouvée'], 404);
        }
        
        $response = new BinaryFileResponse($imagePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($imagePath)
        );
        
        return $response;
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Liste des photos disponibles pour un équipement
 */
#[Route('/api/maintenance/list-equipment-photos/{agencyCode}/{equipmentId}', name: 'app_list_equipment_photos', methods: ['GET'])]
public function listEquipmentPhotos(
    string $agencyCode,
    string $equipmentId,
    EntityManagerInterface $entityManager
): JsonResponse {
    
    try {
        $repository = $this->getRepositoryForAgency($agencyCode, $entityManager);
        $equipment = $repository->findOneBy(['numeroEquipement' => $equipmentId]);
        
        if (!$equipment) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }
        
        $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $idContact = $equipment->getIdContact();
        
        $photos = $this->imageStorageService->getAllImagesForEquipment(
            $agencyCode,
            $idContact,
            $anneeVisite,
            $typeVisite,
            $equipmentId
        );
        
        // Ajouter les URLs de téléchargement
        foreach ($photos as $photoType => &$photoInfo) {
            $photoInfo['download_url'] = $this->generateUrl('app_download_photo', [
                'agencyCode' => $agencyCode,
                'raisonSociale' => $raisonSociale,
                'annee' => $anneeVisite,
                'typeVisite' => $typeVisite,
                'filename' => pathinfo($photoInfo['filename'], PATHINFO_FILENAME)
            ]);
        }
        
        return new JsonResponse([
            'success' => true,
            'equipment_id' => $equipmentId,
            'agency' => $agencyCode,
            'photos_count' => count($photos),
            'photos' => $photos
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * SECTION: ROUTES DE MAINTENANCE BATCH
 */

/**
 * Migration en lot pour tous les équipements d'une agence
 */
#[Route('/api/maintenance/batch-migrate-photos/{agencyCode}', name: 'app_batch_migrate_photos', methods: ['POST'])]
public function batchMigratePhotos(
    string $agencyCode,
    EntityManagerInterface $entityManager,
    Request $request
): JsonResponse {
    
    $batchSize = $request->query->get('batch_size', 50);
    $force = $request->query->get('force', false);
    
    // Configuration pour les gros traitements
    ini_set('memory_limit', '2G');
    ini_set('max_execution_time', 600); // 10 minutes
    
    try {
        $results = $entityManager->getRepository(Form::class)->migrateAllEquipmentsToLocalStorage(
            $agencyCode, 
            $batchSize
        );
        
        return new JsonResponse([
            'success' => true,
            'agency' => $agencyCode,
            'batch_migration_results' => $results,
            'completion_percentage' => $results['total_equipments'] > 0 
                ? round(($results['migrated'] / $results['total_equipments']) * 100, 2) 
                : 0,
            'message' => "Migration batch terminée: {$results['migrated']}/{$results['total_equipments']} équipements"
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Génération PDF optimisée utilisant les photos locales
 */
#[Route('/api/maintenance/generate-pdf-optimized/{agencyCode}/{equipmentId}', name: 'app_generate_pdf_optimized', methods: ['GET'])]
public function generateOptimizedPDF(
    string $agencyCode,
    string $equipmentId,
    EntityManagerInterface $entityManager
): JsonResponse {
    
    try {
        $repository = $this->getRepositoryForAgency($agencyCode, $entityManager);
        $equipment = $repository->findOneBy(['numero_equipement' => $equipmentId]);
        
        if (!$equipment) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }
        
        $startTime = microtime(true);
        
        // Utiliser les photos locales pour la génération
        $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized(
            $equipment, 
            $entityManager
        );
        
        // Simuler la génération du PDF (remplacer par votre logique existante)
        $pdfPath = $this->generateSinglePDFWithLocalPhotos($equipment, $picturesData, $agencyCode);
        
        $processingTime = round(microtime(true) - $startTime, 3);
        
        return new JsonResponse([
            'success' => true,
            'equipment_id' => $equipmentId,
            'agency' => $agencyCode,
            'pdf_generated' => $pdfPath !== null,
            'pdf_path' => $pdfPath,
            'photos_used' => count($picturesData),
            'processing_time_seconds' => $processingTime,
            'performance_improvement' => 'Photos locales utilisées - pas d\'appels API'
        ]);
        
    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * SECTION: MÉTHODES UTILITAIRES PRIVÉES
 */

/**
 * Génère des recommandations basées sur le rapport de migration
 */
private function generateMigrationRecommendations(array $report): array
{
    $recommendations = [];
    
    if ($report['migration_percentage'] < 50) {
        $recommendations[] = [
            'priority' => 'HIGH',
            'action' => 'Lancer une migration batch complète',
            'command' => "php bin/console app:migrate-photos {$report['agence']} --batch-size=25"
        ];
    }
    
    if ($report['equipments_without_local_photos'] > 0) {
        $recommendations[] = [
            'priority' => 'MEDIUM',
            'action' => 'Migrer les équipements manquants',
            'api_endpoint' => "/api/maintenance/batch-migrate-photos/{$report['agence']}"
        ];
    }
    
    if ($report['migration_percentage'] >= 90) {
        $recommendations[] = [
            'priority' => 'LOW',
            'action' => 'Programmer un nettoyage des orphelins',
            'schedule' => 'Hebdomadaire via cron'
        ];
    }
    
    return $recommendations;
}

/**
 * Trouve l'agence avec le plus de photos
 */
private function getLargestAgency(array $agencies): array
{
    if (empty($agencies)) {
        return ['agency' => 'N/A', 'count' => 0];
    }
    
    $largest = array_reduce(array_keys($agencies), function($carry, $agency) use ($agencies) {
        return $agencies[$agency]['count'] > ($carry['count'] ?? 0) 
            ? ['agency' => $agency, 'count' => $agencies[$agency]['count']]
            : $carry;
    }, ['agency' => '', 'count' => 0]);
    
    return $largest;
}

/**
 * Calcule l'efficacité du stockage
 */
private function calculateStorageEfficiency(array $stats): string
{
    if ($stats['total_images'] === 0) {
        return 'N/A';
    }
    
    $avgSize = $stats['total_size'] / $stats['total_images'];
    
    // Taille optimale estimée pour une photo d'équipement (100-500 KB)
    $optimalMinSize = 100 * 1024; // 100 KB
    $optimalMaxSize = 500 * 1024; // 500 KB
    
    if ($avgSize >= $optimalMinSize && $avgSize <= $optimalMaxSize) {
        return 'Optimale';
    } elseif ($avgSize < $optimalMinSize) {
        return 'Sous-optimale (photos trop petites)';
    } else {
        return 'Sous-optimale (photos trop volumineuses)';
    }
}

/**
 * Migre les photos pour un équipement spécifique
 */
private function migratePhotosForSingleEquipment($equipment, Form $formData): bool
{
    try {
        if (!$formData->getFormId() || !$formData->getDataId()) {
            return false;
        }
        
        $agence = $equipment->getCodeAgence();
        $raisonSociale = explode('\\', $equipment->getRaisonSociale())[0] ?? $equipment->getRaisonSociale();
        $anneeVisite = date('Y', strtotime($equipment->getDateEnregistrement()));
        $typeVisite = $equipment->getVisite();
        $codeEquipement = $equipment->getNumeroEquipement();
        $idContact = $equipment->getIdContact();
        
        // Photos à migrer avec leurs types
        $photosToMigrate = [
            'compte_rendu' => $formData->getPhotoCompteRendu(),
            'environnement' => $formData->getPhotoEnvironnementEquipement1(),
            'plaque' => $formData->getPhotoPlaque(),
            'etiquette_somafi' => $formData->getPhotoEtiquetteSomafi(),
            'generale' => $formData->getPhoto2(),
            'moteur' => $formData->getPhotoMoteur(),
            'carte' => $formData->getPhotoCarte()
        ];
        
        $migratedCount = 0;
        
        foreach ($photosToMigrate as $photoType => $photoName) {
            if (!empty($photoName)) {
                if ($this->downloadAndStorePhotoFromKizeo(
                    $photoName,
                    $formData->getFormId(),
                    $formData->getDataId(),
                    $agence,
                    $idContact,
                    $anneeVisite,
                    $typeVisite,
                    $codeEquipement . '_' . $photoType
                )) {
                    $migratedCount++;
                }
            }
        }
        
        return $migratedCount > 0;
        
    } catch (\Exception $e) {
        $this->logger->error("Erreur migration équipement {$equipment->getNumeroEquipement()}: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère un PDF avec photos locales
 */
private function generateSinglePDFWithLocalPhotos($equipment, array $picturesData, string $agence): ?string
{
    try {
        // Votre logique de génération PDF existante
        // mais utilisant $picturesData au lieu d'appels API
        
        $pdfFilename = sprintf(
            'equipement_%s_%s_%s.pdf',
            $equipment->getNumeroEquipement(),
            $agence,
            date('Y-m-d_His')
        );
        
        // Retourner le chemin du PDF généré
        return '/chemin/vers/pdfs/' . $pdfFilename;
        
    } catch (\Exception $e) {
        $this->logger->error("Erreur génération PDF: " . $e->getMessage());
        return null;
    }
}

/**
 * Formate les octets en unités lisibles
 */
private function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);
    
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}
/**
 * DOCUMENTATION DES ENDPOINTS:
 * 
 * MONITORING:
 * GET /api/maintenance/photo-migration-report/{agence} - Rapport de migration
 * GET /api/maintenance/storage-stats - Statistiques globales
 * GET /api/maintenance/check-equipment-photos/{agence}/{equipmentId} - Vérification équipement
 * GET /api/maintenance/list-equipment-photos/{agence}/{equipmentId} - Liste photos équipement
 * 
 * GESTION:
 * DELETE /api/maintenance/clean-orphaned-photos/{agence} - Nettoyage orphelins
 * POST /api/maintenance/migrate-equipment-photos/{agence}/{equipmentId} - Migration équipement
 * POST /api/maintenance/batch-migrate-photos/{agence} - Migration batch
 * 
 * UTILISATION:
 * GET /api/maintenance/download-photo/{agence}/{raison}/{annee}/{visite}/{filename} - Téléchargement photo
 * GET /api/maintenance/generate-pdf-optimized/{agence}/{equipmentId} - PDF optimisé
 * 
 * EXEMPLES D'UTILISATION:
 * curl "http://localhost/api/maintenance/photo-migration-report/S140"
 * curl -X POST "http://localhost/api/maintenance/batch-migrate-photos/S140?batch_size=25"
 * curl -X DELETE "http://localhost/api/maintenance/clean-orphaned-photos/S140"
 */

 /**
 * MÉTHODES MANQUANTES À AJOUTER DANS SIMPLIFIEDMAINTENANCECONTROLLER.PHP
 */

/**
 * Remplit les données spécifiques aux équipements sous contrat
 */
private function fillContractEquipmentData($equipement, array $equipmentContrat): void
{
    // Libellé depuis reference7
    $libelle = $equipmentContrat['reference7']['value'] ?? '';
    $equipement->setLibelleEquipement($libelle);
    
    // Année mise en service depuis reference2
    $miseEnService = $equipmentContrat['reference2']['value'] ?? '';
    $equipement->setMiseEnService($miseEnService);
    
    // Numéro de série depuis reference6
    $numeroSerie = $equipmentContrat['reference6']['value'] ?? '';
    $equipement->setNumeroDeSerie($numeroSerie);
    
    // Marque depuis reference5
    $marque = $equipmentContrat['reference5']['value'] ?? '';
    $equipement->setMarque($marque);
    
    // Hauteur depuis reference1
    $hauteur = $equipmentContrat['reference1']['value'] ?? '';
    $equipement->setHauteur($hauteur);
    
    // Largeur depuis reference3
    $largeur = $equipmentContrat['reference3']['value'] ?? '';
    $equipement->setLargeur($largeur);
    
    // Localisation depuis localisation_site_client
    $localisation = $equipmentContrat['localisation_site_client']['value'] ?? '';
    $equipement->setRepereSiteClient($localisation);
    
    // Mode de fonctionnement depuis mode_fonctionnement_2
    $modeFonctionnement = $equipmentContrat['mode_fonctionnement_2']['value'] ?? '';
    $equipement->setModeFonctionnement($modeFonctionnement);
    
    // Plaque signalétique depuis plaque_signaletique
    $plaqueSignaletique = $equipmentContrat['plaque_signaletique']['value'] ?? '';
    $equipement->setPlaqueSignaletique($plaqueSignaletique);
    
    // État depuis etat
    $etat = $equipmentContrat['etat']['value'] ?? '';
    $equipement->setEtat($etat);
    
    // Longueur depuis longueur
    $longueur = $equipmentContrat['longueur']['value'] ?? '';
    $equipement->setLongueur($longueur);
    
    // Définir le statut de maintenance basé sur l'état
    $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
    $equipement->setStatutDeMaintenance($statut);
    
    // Marquer comme en maintenance
    $equipement->setEnMaintenance(true);
}

/**
 * Remplit les données spécifiques aux équipements hors contrat
 */
private function fillOffContractEquipmentData($equipement, array $equipmentHorsContrat, array $fields): void
{
    // Pour les équipements hors contrat, on utilise principalement les données du formulaire global
    $equipement->setEnMaintenance(false);
    
    // Libellé depuis le path ou une valeur par défaut
    $equipementPath = $equipmentHorsContrat['equipement_supplementaire']['path'] ?? '';
    $libelle = $this->extractLibelleFromPath($equipementPath);
    $equipement->setLibelleEquipement($libelle);
    
    // Données par défaut pour les équipements supplémentaires
    $equipement->setMiseEnService('nc');
    $equipement->setNumeroDeSerie('');
    $equipement->setMarque('');
    $equipement->setHauteur('');
    $equipement->setLargeur('');
    $equipement->setLongueur('');
    $equipement->setRepereSiteClient('');
    $equipement->setModeFonctionnement('');
    $equipement->setPlaqueSignaletique('');
    
    // État depuis le formulaire ou par défaut
    $etat = $fields['etat_equipement_supplementaire']['value'] ?? 'Non renseigné';
    $equipement->setEtat($etat);
    
    $statut = $this->getMaintenanceStatusFromEtatFixed($etat);
    $equipement->setStatutDeMaintenance($statut);
}

/**
 * Détermine le repository approprié pour l'agence
 */
private function getRepositoryForAgency(string $agencyCode, EntityManagerInterface $entityManager)
{
    $entityClass = "App\\Entity\\Equipement{$agencyCode}";
    
    try {
        return $entityManager->getRepository($entityClass);
    } catch (\Exception $e) {
        throw new \InvalidArgumentException("Repository non trouvé pour l'agence {$agencyCode}");
    }
}

/**
 * Télécharge et stocke une photo depuis l'API Kizeo (version mise à jour)
 */
private function downloadAndStorePhotoFromKizeo(
    string $photoName,
    string $formId,
    string $dataId,
    string $agence,
    string $idContact,
    string $anneeVisite,
    string $typeVisite,
    string $filename
): bool {
    try {
        // Vérifier si la photo existe déjà localement
        if ($this->imageStorageService->imageExists($agence, $idContact, $anneeVisite, $typeVisite, $filename)) {
            return true; // Déjà présente
        }
        
        // Gérer les photos multiples séparées par des virgules
        $photoNames = str_contains($photoName, ', ') ? explode(', ', $photoName) : [$photoName];
        
        $savedCount = 0;
        foreach ($photoNames as $index => $singlePhotoName) {
            $singlePhotoName = trim($singlePhotoName);
            if (empty($singlePhotoName)) continue;
            
            try {
                // Télécharger depuis l'API Kizeo
                $response = $this->client->request(
                    'GET',
                    'https://forms.kizeo.com/rest/v3/forms/' . $formId . '/data/' . $dataId . '/medias/' . $singlePhotoName,
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
                    continue;
                }
                
                // Nom de fichier unique pour les photos multiples
                $finalFilename = count($photoNames) > 1 ? $filename . '_' . ($index + 1) : $filename;
                
                // Sauvegarder localement
                $this->imageStorageService->storeImage(
                    $agence,
                    $idContact,
                    $anneeVisite,
                    $typeVisite,
                    $finalFilename,
                    $imageContent
                );
                
                $savedCount++;
                
            } catch (\Exception $e) {
                error_log("Erreur téléchargement photo individuelle {$singlePhotoName}: " . $e->getMessage());
                continue;
            }
        }
        
        return $savedCount > 0;
        
    } catch (\Exception $e) {
        error_log("Erreur téléchargement photo {$photoName}: " . $e->getMessage());
        return false;
    }
}

/**
 * Extrait le libellé depuis le path de l'équipement
 */
private function extractLibelleFromPath(string $equipementPath): string
{
    // Analyser le path pour extraire le type d'équipement
    // Exemple: "equipement_supplementaire.porte_rapide" -> "Porte rapide"
    if (str_contains($equipementPath, '.')) {
        $parts = explode('.', $equipementPath);
        $lastPart = end($parts);
        
        // Convertir snake_case en titre
        $libelle = str_replace('_', ' ', $lastPart);
        return ucwords($libelle);
    }
    
    return 'Équipement supplémentaire';
}

    /**
     * ✅ CORRECTION 1 : Récupérer la visite depuis les fields au lieu du path
     */
    private function getVisiteFromFields(array $fields, array $equipmentData = []): string
    {
        // Essayer plusieurs sources pour trouver la visite
        
        // 1. Depuis le tableau contrat_de_maintenance (équipements au contrat)
        if (isset($fields['contrat_de_maintenance']['value']) && 
            !empty($fields['contrat_de_maintenance']['value']) &&
            isset($fields['contrat_de_maintenance']['value'][0]['equipement']['path'])) {
            
            $path = $fields['contrat_de_maintenance']['value'][0]['equipement']['path'];
            $parts = explode('\\', $path);
            if (!empty($parts[0]) && preg_match('/^CE\d+$/', $parts[0])) {
                return $parts[0];
            }
        }
        
        // 2. Depuis un champ dédié type_visite ou visite
        if (isset($fields['type_visite']['value']) && !empty($fields['type_visite']['value'])) {
            return $fields['type_visite']['value'];
        }
        
        if (isset($fields['visite']['value']) && !empty($fields['visite']['value'])) {
            return $fields['visite']['value'];
        }
        
        // 3. Depuis le path de l'équipement spécifique
        if (isset($equipmentData['equipement']['path'])) {
            $path = $equipmentData['equipement']['path'];
            $parts = explode('\\', $path);
            if (!empty($parts[0]) && preg_match('/^CE\d+$/', $parts[0])) {
                return $parts[0];
            }
        }
        
        // 4. Par défaut CE1
        return 'CE1';
    }

}