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
     * Traiter les formulaires de maintenance par agence en utilisant les méthodes existantes
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
        ini_set('memory_limit', '2G'); // Augmentation à 2G
        ini_set('max_execution_time', 0); // Pas de limite de temps
        set_time_limit(0); // Pas de limite de temps
        
        // Forcer le garbage collector
        gc_enable();
        
        $validAgencies = ['S10', 'S40', 'S50', 'S60', 'S70', 'S80', 'S100', 'S120', 'S130', 'S140', 'S150', 'S160', 'S170'];
        
        if (!in_array($agencyCode, $validAgencies)) {
            return new JsonResponse(['error' => 'Code agence non valide: ' . $agencyCode], 400);
        }

        try {
            // Récupérer les données de maintenance en utilisant la méthode existante
            $maintenanceData = $formRepository->getDataOfFormsMaintenance($cache);
            
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

            return new JsonResponse([
                'success' => true,
                'agency' => $agencyCode,
                'processed' => $processed,
                'contract_equipments' => $contractEquipments,
                'off_contract_equipments' => $offContractEquipments,
                'errors' => $errors,
                'message' => "Traitement terminé: {$processed} formulaires, {$contractEquipments} équipements contrat, {$offContractEquipments} équipements hors contrat"
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du traitement: ' . $e->getMessage(),
                'agency' => $agencyCode
            ], 500);
        }
    }

    /**
     * Traiter un formulaire spécifique pour une agence
     */
    private function processAgencyForm(array $fields, string $agencyCode, EntityManagerInterface $entityManager): array
    {
        $entityClass = $this->getEntityClassByAgency($agencyCode);
        
        if (!$entityClass) {
            throw new \Exception("Classe d'entité non trouvée pour l'agence: {$agencyCode}");
        }

        $results = [
            'contract' => 0,
            'off_contract' => 0
        ];

        // Traitement des équipements AU CONTRAT
        if (isset($fields['contrat_de_maintenance']['value']) && !empty($fields['contrat_de_maintenance']['value'])) {
            foreach ($fields['contrat_de_maintenance']['value'] as $equipmentContrat) {
                try {
                    $equipement = new $entityClass();
                    
                    // Données communes
                    $this->setCommonEquipmentData($equipement, $fields);
                    
                    // Données spécifiques équipement au contrat
                    $this->setContractEquipmentData($equipement, $equipmentContrat);
                    
                    $entityManager->persist($equipement);
                    $results['contract']++;
                    
                } catch (\Exception $e) {
                    error_log("Erreur équipement contrat: " . $e->getMessage());
                }
            }
        }

        // Traitement des équipements HORS CONTRAT
        if (isset($fields['tableau2']['value']) && !empty($fields['tableau2']['value'])) {
            foreach ($fields['tableau2']['value'] as $equipmentHorsContrat) {
                try {
                    $equipement = new $entityClass();
                    
                    // Données communes
                    $this->setCommonEquipmentData($equipement, $fields);
                    
                    // Données spécifiques équipement hors contrat avec numérotation auto
                    $this->setOffContractEquipmentData($equipement, $equipmentHorsContrat, $fields, $entityClass, $entityManager);
                    
                    $entityManager->persist($equipement);
                    $results['off_contract']++;
                    
                } catch (\Exception $e) {
                    error_log("Erreur équipement hors contrat: " . $e->getMessage());
                }
            }
        }

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            error_log("Erreur flush: " . $e->getMessage());
            throw $e;
        }

        return $results;
    }

    /**
     * Définir les données communes à tous les équipements
     */
    private function setCommonEquipmentData($equipement, array $fields): void
    {
        $equipement->setIdContact($fields['id_client_']['value'] ?? '');
        $equipement->setRaisonSociale($fields['nom_client']['value'] ?? '');
        $equipement->setDateEnregistrement($fields['date_et_heure1']['value'] ?? new \DateTime());
        $equipement->setCodeSociete($fields['id_societe']['value'] ?? '');
        $equipement->setCodeAgence($fields['id_agence']['value'] ?? '');
        $equipement->setDerniereVisite($fields['date_et_heure1']['value'] ?? new \DateTime());
        $equipement->setTrigrammeTech($fields['trigramme']['value'] ?? '');
        $equipement->setSignatureTech($fields['signature3']['value'] ?? '');
    }

    /**
     * Définir les données spécifiques aux équipements au contrat
     */
    private function setContractEquipmentData($equipement, array $equipmentContrat): void
    {
        // Extraction du numéro d'équipement depuis le path
        $equipementPath = $equipmentContrat['equipement']['path'] ?? '';
        $equipementValue = $equipmentContrat['equipement']['value'] ?? '';
        
        // Détermination du type de visite
        $visite = $this->extractVisitTypeFromPath($equipementPath);
        $equipement->setVisite($visite);
        
        // Extraction des informations d'équipement depuis la valeur structurée
        $equipmentInfo = $this->parseEquipmentInfo($equipementValue);
        
        $equipement->setNumeroEquipement($equipmentInfo['numero'] ?? '');
        $equipement->setLibelleEquipement($equipmentInfo['libelle'] ?? '');
        $equipement->setMiseEnService($equipmentInfo['mise_en_service'] ?? '');
        $equipement->setNumeroDeSerie($equipmentInfo['numero_serie'] ?? '');
        $equipement->setMarque($equipmentInfo['marque'] ?? '');
        $equipement->setHauteur($equipmentInfo['hauteur'] ?? '');
        $equipement->setLargeur($equipmentInfo['largeur'] ?? '');
        $equipement->setRepereSiteClient($equipmentInfo['repere'] ?? '');
        
        // Données spécifiques du formulaire
        $equipement->setModeFonctionnement($equipmentContrat['mode_fonctionnement']['value'] ?? '');
        $equipement->setLongueur($equipmentContrat['longueur']['value'] ?? 'NC');
        $equipement->setPlaqueSignaletique($equipmentContrat['plaque_signaletique']['value'] ?? '');
        $equipement->setEtat($equipmentContrat['etat']['value'] ?? '');
        
        // Statut de maintenance basé sur l'état
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
        
        // Génération automatique du numéro d'équipement
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
        
        // Détermination du type de visite par défaut
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

    // --- MÉTHODES UTILITAIRES ---

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
        // Parse de la structure "ATEIS\CEA\SEC01|Porte sectionnelle|..."
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
            case "Fonctionnement ok":
                return "Vert";
            case "Travaux à prévoir":
                return "Orange";
            case "Travaux obligatoires":
            case "Equipement mis à l'arrêt lors de l'intervention":
                return "Rouge";
            case "Equipement inaccessible le jour de la visite":
                return "Inaccessible";
            case "Equipement à l'arrêt le jour de la visite":
                return "A l'arrêt";
            case "Equipement non présent sur site":
                return "Non présent";
            default:
                return "NC";
        }
    }

    private function getTypeCodeFromLibelle(string $libelle): string
    {
        $typeMap = [
            'porte sectionnelle' => 'SEC',
            'portail coulissant' => 'COU',
            'portail battant' => 'BAT',
            'barrière levante' => 'LEV',
            'porte basculante' => 'BAS',
            'rideau métallique' => 'RID',
            'porte rapide' => 'RAP',
            'tourniquets' => 'TOU',
            'sas' => 'SAS',
            'divers' => 'DIV'
        ];
        
        foreach ($typeMap as $key => $code) {
            if (str_contains($libelle, $key)) {
                return $code;
            }
        }
        
        return 'DIV';
    }

    private function getNextEquipmentNumber(string $typeCode, string $idClient, string $entityClass, EntityManagerInterface $entityManager): int
    {
        $repository = $entityManager->getRepository($entityClass);
        
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
}