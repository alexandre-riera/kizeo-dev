<?php

namespace App\Controller;

use DateTime;
use DOMDocument;
use DateInterval;
use App\Entity\Form;
use App\Entity\Agency;
use App\Entity\User;
use App\Form\AgencyType;
use App\Entity\ContactS10;
use App\Entity\ContactS40;
use App\Entity\ContactS50;
use App\Entity\ContactS60;
use App\Entity\ContactS70;
use App\Entity\ContactS80;
use App\Entity\ContactS100;
use App\Entity\ContactS120;
use App\Entity\ContactS130;
use App\Entity\ContactS140;
use App\Entity\ContactS150;
use App\Entity\ContactS160;
use App\Entity\ContactS170;
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
use App\Repository\HomeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserInterface;

class HomeController extends AbstractController
{

    #[Route('/', name: 'app_front')]
    public function index(CacheInterface $cache, EntityManagerInterface $entityManager, SerializerInterface $serializer, Request $request, HomeRepository $homeRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les codes d'agence de l'utilisateur
        $userAgencies = $this->getUserAgencies($user);
        
        // Si l'utilisateur n'a aucun rôle d'agence, rediriger ou afficher un message d'erreur
        if (empty($userAgencies)) {
            $this->addFlash('error', 'Tu n\'as accès à aucune agence. Contacte l\'administrateur.');
            return $this->redirectToRoute('app_logout');
        }

        // GET CONTACTS KIZEO BY AGENCY
        $clientsGroup = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_GROUP"]);
        $clientsStEtienne = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_ST_ETIENNE"]);
        $clientsGrenoble = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_GRENOBLE"]);
        $clientsLyon = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_LYON"]);
        $clientsBordeaux = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_BORDEAUX"]);
        $clientsParisNord = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_PARIS_NORD"]);
        $clientsMontpellier = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_MONTPELLIER"]);
        $clientsHautsDeFrance = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_HAUTS_DE_FRANCE"]);
        $clientsToulouse = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_TOULOUSE"]);
        $clientsEpinal = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_EPINAL"]);
        $clientsPaca = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_PACA"]);
        $clientsRouen = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_ROUEN"]);
        $clientsRennes = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_RENNES"]);
        
        // Variables pour le template
        $agenceSelected = null;
        $clientSelected = null;
        $clientSelectedInformations = null;
        $clientSelectedEquipments = [];
        $clientSelectedEquipmentsFiltered = [];
        $clientSelectedEquipmentsFilteredAuContrat = [];
        $clientSelectedEquipmentsFilteredHorsContrat = [];
        $idClientSelected = "";
        $directoriesLists = [];
        
        // **ÉTAPE 1 : Logique de sélection d'agence**
        if (count($userAgencies) === 1) {
            // Un seul rôle d'agence : sélection automatique
            $agenceSelected = $userAgencies[0];
        } elseif (count($userAgencies) > 1) {
            // Plusieurs rôles : vérifier si une agence a été soumise
            if ($request->isMethod('POST') && $request->request->has('agenceName')) {
                $selectedAgency = $request->request->get('agenceName');
                // Vérifier que l'agence sélectionnée est dans les rôles de l'utilisateur
                if (in_array($selectedAgency, $userAgencies)) {
                    $agenceSelected = $selectedAgency;
                }
            }
            
            // Récupérer l'agence depuis le champ caché si présent
            if ($request->isMethod('POST') && $request->request->has('hiddenAgence')) {
                $hiddenAgency = $request->request->get('hiddenAgence');
                if (in_array($hiddenAgency, $userAgencies)) {
                    $agenceSelected = $hiddenAgency;
                }
            }
        }

        // **ÉTAPE 2 : Logique de sélection de client**
        if (isset($_POST['clientName']) && !empty($agenceSelected)) {
            $clientSelected = $_POST['clientName'];

            // Extraire seulement l'ID et le nom, pas l'agence
            if ($clientSelected != "") {
                $clientSelectedSplitted = preg_split("/[-]/", $clientSelected, 2); // Limiter à 2 parties max
                if (count($clientSelectedSplitted) >= 2) {
                    $idClientSelected = trim($clientSelectedSplitted[0]);
                    $clientSelected = trim($clientSelectedSplitted[1]);
                    
                    // Charger les informations et équipements du client
                    $this->loadClientData($agenceSelected, $idClientSelected, $entityManager, $clientSelectedInformations, $clientSelectedEquipments, $homeRepository, $idClientSelected);
                }
            }
        }

        // **ÉTAPE 3 : Gestion des filtres**
        $clientAnneeFilterArray = [];
        $clientVisiteFilterArray = [];
        $clientAnneeFilter = "";
        $clientVisiteFilter = "";
        $defaultYear = "";
        $defaultVisit = "";

        if (!empty($clientSelectedEquipments)) {
            // Construire les arrays de filtres et trouver les valeurs par défaut
            $this->buildFiltersAndDefaults(
                $clientSelectedEquipments, 
                $clientAnneeFilterArray, 
                $clientVisiteFilterArray, 
                $defaultYear, 
                $defaultVisit
            );

            // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

            // Validation des filtres si soumis
            if ($request->query->get('submitFilters')) {
                $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
                $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
                
                if (empty($clientAnneeFilter)) {
                    $this->addFlash('error', 'Sélectionne l\'année.');
                }
                if (empty($clientVisiteFilter)) {
                    $this->addFlash('error', 'Sélectionne la visite.');
                }
            }

            // **REFACTORISATION : Utiliser la méthode privée pour le filtrage**
            $equipmentData = $this->filterEquipments(
                $clientSelectedEquipments, 
                $clientAnneeFilter ?: $defaultYear, 
                $clientVisiteFilter ?: $defaultVisit
            );
            
            $clientSelectedEquipmentsFiltered = $equipmentData['filtered'];
            $clientSelectedEquipmentsFilteredAuContrat = $equipmentData['auContrat'];
            $clientSelectedEquipmentsFilteredHorsContrat = $equipmentData['horsContrat'];

            // Si aucun équipement filtré, montrer tous
            if (empty($clientSelectedEquipmentsFiltered)) {
                $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
            }

            // Générer la liste des PDF
            if (!empty($clientSelectedEquipmentsFiltered)) {
                $dateArray = $this->generateDateArray($clientSelectedEquipmentsFiltered);
                
                if (!empty($clientVisiteFilter ?: $defaultVisit) && $agenceSelected) {
                    $directoriesLists = $homeRepository->getListOfPdf($clientSelected, ($clientVisiteFilter ?: $defaultVisit), $agenceSelected, $dateArray);
                }
            }
        }

        return $this->render('home/index.html.twig', [
            'userAgencies' => $userAgencies,
            'clientsGroup' => $clientsGroup,
            'clientsStEtienne' => $clientsStEtienne,
            'clientsGrenoble' => $clientsGrenoble,
            'clientsLyon' => $clientsLyon,
            'clientsBordeaux' => $clientsBordeaux,
            'clientsParisNord' => $clientsParisNord,
            'clientsMontpellier' => $clientsMontpellier,
            'clientsHautsDeFrance' => $clientsHautsDeFrance,
            'clientsToulouse' => $clientsToulouse,
            'clientsEpinal' => $clientsEpinal,
            'clientsPaca' => $clientsPaca,
            'clientsRouen' => $clientsRouen,
            'clientsRennes' => $clientsRennes,
            'clientSelected' => $clientSelected,
            'agenceSelected' => $agenceSelected,
            'clientSelectedInformations' => $clientSelectedInformations,
            'clientSelectedEquipmentsFiltered' => $clientSelectedEquipmentsFiltered,
            'clientSelectedEquipmentsFilteredAuContrat' => $clientSelectedEquipmentsFilteredAuContrat,
            'clientSelectedEquipmentsFilteredHorsContrat' => $clientSelectedEquipmentsFilteredHorsContrat,
            'totalClientSelectedEquipmentsFiltered' => count($clientSelectedEquipmentsFiltered),
            'directoriesLists' => $directoriesLists,
            'clientSelectedEquipments' => $clientSelectedEquipments,
            'idClientSelected' => $idClientSelected,
            'clientAnneeFilterArray' => $clientAnneeFilterArray,
            'clientAnneeFilter' => $clientAnneeFilter,
            'clientVisiteFilterArray' => $clientVisiteFilterArray,
            'clientVisiteFilter' => $clientVisiteFilter,
            'defaultYear' => $defaultYear,
            'defaultVisit' => $defaultVisit,
        ]);
    }

    // ===========================
    // MÉTHODES PRIVÉES EXTRAITES  
    // ===========================

    /**
     * Construit les tableaux de filtres et trouve les valeurs par défaut
     */
    private function buildFiltersAndDefaults(
        array $clientSelectedEquipments, 
        array &$clientAnneeFilterArray, 
        array &$clientVisiteFilterArray, 
        string &$defaultYear, 
        string &$defaultVisit
    ): void {
        $absoluteLatestVisitDate = null;
        
        foreach ($clientSelectedEquipments as $equipment) {
            // Construire le tableau des années
            if ($equipment->getDerniereVisite() !== null) {
                $date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                if (!in_array($date_equipment, $clientAnneeFilterArray)) {
                    $clientAnneeFilterArray[] = $date_equipment;
                }
                
                // Trouver la date la plus récente
                $currentDate = new DateTime($equipment->getDerniereVisite());
                if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                    $absoluteLatestVisitDate = $currentDate;
                }
            }
            
            // Construire le tableau des visites
            $visite_equipment = $equipment->getVisite();
            if (!in_array($visite_equipment, $clientVisiteFilterArray)) {
                $clientVisiteFilterArray[] = $visite_equipment;
            }
        }

        // Définir les valeurs par défaut
        if ($absoluteLatestVisitDate) {
            $defaultYear = $absoluteLatestVisitDate->format('Y');
            
            // Trouver la visite correspondant à la date la plus récente
            foreach ($clientSelectedEquipments as $equipment) {
                if ($equipment->getDerniereVisite() !== null) {
                    $equipmentDate = new DateTime($equipment->getDerniereVisite());
                    if ($equipmentDate == $absoluteLatestVisitDate) {
                        $defaultVisit = $equipment->getVisite();
                        break;
                    }
                }
            }
        }
    }

    /**
     * Filtre les équipements selon l'année et la visite
     * Retourne un tableau avec les équipements filtrés et séparés par type de contrat
     */
    private function filterEquipments(array $equipments, string $anneeFilter, string $visiteFilter): array
    {
        $filtered = array_filter($equipments, function($equipment) use ($anneeFilter, $visiteFilter) {
            // Vérifier que l'équipement a une date de dernière visite
            if (!$equipment->getDerniereVisite()) {
                return false;
            }

            // Filtrage par année
            $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
            $matchesAnnee = ($annee_date_equipment == $anneeFilter);

            // Filtrage par visite
            $matchesVisite = ($equipment->getVisite() == $visiteFilter);

            return $matchesAnnee && $matchesVisite;
        });

        // Séparation par type de contrat
        $auContrat = array_filter($filtered, function($equipment) {
            return $equipment->isEnMaintenance() === true;
        });

        $horsContrat = array_filter($filtered, function($equipment) {
            return $equipment->isEnMaintenance() === false;
        });

        return [
            'filtered' => $filtered,
            'auContrat' => $auContrat,
            'horsContrat' => $horsContrat
        ];
    }

    /**
     * Génère le tableau des dates pour les PDF
     */
    private function generateDateArray(array $equipments): array
    {
        $dateArray = [];
        foreach($equipments as $equipment) {
            if ($equipment->getDerniereVisite() && !in_array($equipment->getDerniereVisite(), $dateArray)) {
                $dateArray[] = $equipment->getDerniereVisite();
            }
        }
        return $dateArray;
    }

    /**
     * Méthode pour charger les données du client selon l'agence
     * CORRECTION: Paramètre HomeRepository non nullable
     */
    private function loadClientData(string $agenceSelected, string $idClientSelected, EntityManagerInterface $entityManager, &$clientSelectedInformations, array &$clientSelectedEquipments, ?HomeRepository $homeRepository, string $clientSelected): void
    {
        switch ($agenceSelected) {
            case 'S10':
                $clientSelectedInformations = $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S40':
                $clientSelectedInformations = $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S50':
                $clientSelectedInformations = $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S60':
                $clientSelectedInformations = $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S70':
                $clientSelectedInformations = $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S80':
                $clientSelectedInformations = $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S100':
                $clientSelectedInformations = $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S120':
                $clientSelectedInformations = $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S130':
                $clientSelectedInformations = $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S140':
                $clientSelectedInformations = $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S150':
                $clientSelectedInformations = $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S160':
                $clientSelectedInformations = $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S170':
                $clientSelectedInformations = $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $idClientSelected]);
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
        }
    }

    /**
     * Extrait les codes d'agence des rôles de l'utilisateur
     */
    private function getUserAgencies(UserInterface $user): array
    {
        $agencies = [];
        $roles = $user->getRoles();
        
        foreach ($roles as $role) {
            // Vérifier si le rôle correspond à un code d'agence
            if (preg_match('/^ROLE_(S\d+)$/', $role, $matches)) {
                $agencies[] = $matches[1]; // Récupère S10, S40, etc.
            }
        }
        
        return array_unique($agencies);
    }
    
    /**
     * Convertit le code d'agence en nom lisible
     */
    private function getAgencyName(string $code): string
    {
        $agencyNames = [
            'S10' => 'Group',
            'S40' => 'St Etienne',
            'S50' => 'Grenoble',
            'S60' => 'Lyon',
            'S70' => 'Bordeaux',
            'S80' => 'ParisNord',
            'S100' => 'Montpellier',
            'S120' => 'HautsDeFrance',
            'S130' => 'Toulouse',
            'S140' => 'SMP',
            'S150' => 'PACA',
            'S160' => 'Rouen',
            'S170' => 'Rennes',
        ];
        
        return $agencyNames[$code] ?? $code;
    }

    #[Route('/ajax/filter-equipment', name: 'app_ajax_filter_equipment')]
    public function ajaxFilterEquipment(Request $request, EntityManagerInterface $entityManager): Response
    {
        try {
            // RÉCUPÉRATION DES PARAMÈTRES
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
            $agenceSelected = $request->query->get('agenceSelected', '');
            $idClientSelected = $request->query->get('idClientSelected', '');
            $clientSelected = $request->query->get('clientSelected', '');
            
            error_log("=== FILTRAGE AJAX ===");
            error_log("Agence: {$agenceSelected}, Client: {$idClientSelected}");
            error_log("Filtres - Année: '{$clientAnneeFilter}', Visite: '{$clientVisiteFilter}'");
            
            // VALIDATION DES PARAMÈTRES
            $errors = [];
            
            if (empty($agenceSelected)) {
                $errors[] = 'Agence non sélectionnée.';
            }
            
            if (empty($idClientSelected)) {
                $errors[] = 'Client non sélectionné.';
            }
            
            if (empty($clientAnneeFilter)) {
                $errors[] = 'Sélectionne l\'année.';
            }
            
            if (empty($clientVisiteFilter)) {
                $errors[] = 'Sélectionne la visite.';
            }
            
            if (!empty($errors)) {
                error_log("Erreurs de validation: " . implode(', ', $errors));
                return $this->json(['errors' => $errors], 400);
            }
            
            // VÉRIFICATION DE L'ACCÈS UTILISATEUR À L'AGENCE
            $user = $this->getUser();
            $userAgencies = $this->getUserAgencies($user);
            
            if (!in_array($agenceSelected, $userAgencies)) {
                error_log("Accès non autorisé à l'agence {$agenceSelected}");
                return $this->json(['error' => 'Accès non autorisé à cette agence'], 403);
            }
            
            // RÉCUPÉRATION DES ÉQUIPEMENTS
            $clientSelectedEquipments = $this->getEquipmentsByAgence($agenceSelected, $idClientSelected, $entityManager);
            error_log("Équipements bruts récupérés: " . count($clientSelectedEquipments));
            
            if (empty($clientSelectedEquipments)) {
                error_log("Aucun équipement trouvé pour ce client");
                return $this->json([
                    'html' => '<div class="alert alert-warning">Aucun équipement trouvé pour ce client.</div>'
                ]);
            }

            // FILTRAGE DES ÉQUIPEMENTS AVEC LA MÉTHODE RÉUTILISABLE
            $equipmentData = $this->filterEquipments($clientSelectedEquipments, $clientAnneeFilter, $clientVisiteFilter);
            
            $clientSelectedEquipmentsFiltered = $equipmentData['filtered'];
            $clientSelectedEquipmentsFilteredAuContrat = $equipmentData['auContrat'];
            $clientSelectedEquipmentsFilteredHorsContrat = $equipmentData['horsContrat'];
            
            error_log("Équipements après filtrage: " . count($clientSelectedEquipmentsFiltered));

            // GÉNÉRATION DU HTML DE RÉPONSE
            if (empty($clientSelectedEquipmentsFiltered)) {
                $html = $this->renderView('components/equipment_table_empty.html.twig', [
                    'message' => 'Aucun équipement ne correspond aux filtres sélectionnés.'
                ]);
            } else {
                $html = $this->renderView('components/equipment_table.html.twig', [
                    'clientSelectedEquipmentsFiltered' => $clientSelectedEquipmentsFiltered,
                    'clientSelectedEquipmentsFilteredAuContrat' => $clientSelectedEquipmentsFilteredAuContrat,
                    'clientSelectedEquipmentsFilteredHorsContrat' => $clientSelectedEquipmentsFilteredHorsContrat
                ]);
            }

            return $this->json(['html' => $html]);

        } catch (\Exception $e) {
            error_log("Erreur AJAX filter equipment: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->json(['error' => 'Erreur lors du filtrage des équipements'], 500);
        }
    }

    /**
     * Méthode helper pour récupérer les équipements par agence
     */
    private function getEquipmentsByAgence(string $agence, string $clientId, EntityManagerInterface $entityManager): array
    {
        try {
            error_log("Récupération équipements pour agence: {$agence}, client: {$clientId}");
            
            switch ($agence) {
                case 'S10':
                    return $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S40':
                    return $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S50':
                    return $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S60':
                    return $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S70':
                    return $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S80':
                    return $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S100':
                    return $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S120':
                    return $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S130':
                    return $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S140':
                    return $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S150':
                    return $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S160':
                    return $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                case 'S170':
                    return $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $clientId], ['numero_equipement' => 'ASC']);
                default:
                    return [];
            }
            
            error_log("Trouvé " . count($equipments) . " équipements pour {$agence}/{$clientId}");
            return $equipments;
            
        } catch (\Exception $e) {
            error_log("Erreur récupération équipements {$agence}/{$clientId}: " . $e->getMessage());
            return [];
        }
    }

    #[Route('/save/modal/equipement', name: 'app_save_modal_equipement')]
    public function saveModalInDatabase(EntityManagerInterface $entityManager, Request $request)//: Response
    {

        // Récupération de l'équipement édité dans la modal
        if(isset($_POST['saveEquipmentFromModal'])){
            $equipmentidEquipementAndIdRow= $_POST['id'];
            $equipmentNom = $_POST['nom'];
            $equipmentPrenom = $_POST['prenom'];
            $equipmentLibelle = $_POST['libelle'];
            $equipmentVisite = $_POST['visite'];
            $equipmentRaisonSociale = $_POST['raisonSociale'];
            $equipmentModeleNacelle = $_POST['modeleNacelle'];
            $equipmentHauteurNacelle = $_POST['hauteurNacelle'];
            $equipmentIfExistDB = $_POST['ifExistDB'];
            $equipmentSignatureTech = $_POST['signatureTech'];
            $equipmentTrigrammeTech = $_POST['trigrammeTech'];
            $equipmentAnomalies = $_POST['anomalies'];
            $equipmentIdContact = $_POST['idContact'];
            $equipmentIdSociete = $_POST['idSociete'];
            $equipmentCodeAgence = $_POST['codeAgence'];
            $equipmentTrigramme = $_POST['trigramme'];
            $equipmentModeFonctionnement = $_POST['modefonctionnement'];
            $equipmentRepereSiteClient = $_POST['reperesiteclient'];
            $equipmentMiseEnService = $_POST['miseenservice'];
            $equipmentNumeroDeSerie = $_POST['numerodeserie'];
            $equipmentMarque = $_POST['marque'];
            $equipmentHauteur = $_POST['hauteur'];
            $equipmentLargeur = $_POST['largeur'];
            $equipmentLongueur = $_POST['longueur'];
            $equipmentPlaqueSignaletique = $_POST['plaquesignaletique'];
            $equipmentEtat = $_POST['etat'];
            $equipmentDerniereVisiteDeMaintenance = $_POST['dernierevisitedemaintenance'];
            $equipmentOldStatut = $_POST['oldstatut'];
            $equipmentNewStatutClient = $_POST['newstatutclient'];
            $equipmentCarnetEntretien = $_POST['carnetentretien'];
            $equipmentStatutConformite = $_POST['statutconformite'];

            // Save IT
            $entityAgency = null;
            switch ($equipmentCodeAgence) {
                case 'S10':
                    $entityAgency = EquipementS10::class;
                    break;
                case 'S40':
                    $entityAgency = EquipementS40::class;
                    break;
                case 'S50':
                    $entityAgency = EquipementS50::class;
                    break;
                case 'S60':
                    $entityAgency = EquipementS60::class;
                    break;
                case 'S70':
                    $entityAgency = EquipementS70::class;
                    break;
                case 'S80':
                    $entityAgency = EquipementS80::class;
                    break;
                case 'S100':
                    $entityAgency = EquipementS100::class;
                    break;
                case 'S120':
                    $entityAgency = EquipementS120::class;
                    break;
                case 'S130':
                    $entityAgency = EquipementS130::class;
                    break;
                case 'S140':
                    $entityAgency = EquipementS140::class;
                    break;
                case 'S150':
                    $entityAgency = EquipementS150::class;
                    break;
                case 'S160':
                    $entityAgency = EquipementS160::class;
                    break;
                case 'S170':
                    $entityAgency = EquipementS170::class;
                    break;
                
                default:
                    # code...
                    break;
            }
            // $equipement = new $entityAgency;
            $equipement = $entityManager->getRepository($entityAgency)->findOneBy(['id' => $equipmentidEquipementAndIdRow]);
            $equipement->setIdContact($equipmentIdContact);
            // Pour avoir l'heure exacte de l'enregistrement
            $date = new DateTime(date("Y-m-d H:i:s"));
            $dateValable = clone $date;
            $dateValable->modify('+2 hour');
            $equipement->setDateEnregistrement($dateValable->format('d-m-Y H:i:s'));
            $equipement->setCodeSociete($equipmentIdSociete);
            $equipement->setCodeAgence($equipmentCodeAgence);
            $equipement->setDerniereVisite($equipmentDerniereVisiteDeMaintenance);
            if (empty($equipmentNom) && empty($equipmentPrenom)) {
                $equipement->setTrigrammeTech($equipmentTrigrammeTech);
            }else{
                $equipement->setTrigrammeTech($equipmentNom . " " . $equipmentPrenom);
            }
            $equipement->setSignatureTech($equipmentSignatureTech);
            $equipement->setVisite($equipmentVisite);
            $equipement->setNumeroEquipement($equipmentTrigramme);
            $equipement->setIfExistDB($equipmentIfExistDB);
            $equipement->setLibelleEquipement(strtolower($equipmentLibelle));
            $equipement->setModeFonctionnement($equipmentModeFonctionnement);
            $equipement->setRepereSiteClient($equipmentRepereSiteClient);
            $equipement->setMiseEnService($equipmentMiseEnService);
            $equipement->setNumeroDeSerie($equipmentNumeroDeSerie);
            $equipement->setMarque($equipmentMarque);
            $equipement->setLargeur($equipmentLargeur);
            $equipement->setHauteur($equipmentHauteur);
            $equipement->setLongueur($equipmentLongueur);
            $equipement->setPlaqueSignaletique($equipmentPlaqueSignaletique);
            $equipement->setAnomalies($equipmentAnomalies);
            $equipement->setEtat($equipmentEtat);
            $equipement->setHauteurNacelle($equipmentHauteurNacelle);
            $equipement->setModeleNacelle($equipmentModeleNacelle);
            if (isset($equipmentNewStatutClient) && $equipmentNewStatutClient != "Choose...") {
                $equipement->setStatutDeMaintenance($equipmentNewStatutClient);
            }else{
                $equipement->setStatutDeMaintenance($equipmentOldStatut);
            }
            $equipement->setRaisonSociale($equipmentRaisonSociale);
            $equipement->setPresenceCarnetEntretien($equipmentCarnetEntretien);
            $equipement->setStatutConformite($equipmentStatutConformite);
            $equipement->setEnMaintenance(true);
            
            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $entityManager->persist($equipement);
            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
        }
        $this->addFlash('success', 'L\'équipement a été mit à jour avec succès !');
        return $this->redirectToRoute('app_show_equipement_details_by_id', [
            'agence' => $equipmentCodeAgence,
            'id' => $equipmentidEquipementAndIdRow
        ]);
        // return new Response("<html><body><p  style='font-size:18px; font-weight:bold;'>L'équipement édité dans la modal a bien été enregistré en base de données</p></body></html>", Response::HTTP_OK, [], true);
        // return $this->redirect($request->getUri());
    }

    #[Route('/show/equipement/details/{agence}/{id}', name: 'app_show_equipement_details_by_id')]
    public function showEquipmentDetailsById(string $agence, string $id, EntityManagerInterface $entityManager){
        switch ($agence) {
            case 'S10':
                $equipment = $entityManager->getRepository(EquipementS10::class)->findOneBy(['id' => $id]); // L'ID remonté est bon 
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S40':
                $equipment = $entityManager->getRepository(EquipementS40::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S50':
                $equipment = $entityManager->getRepository(EquipementS50::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S60':
                $equipment = $entityManager->getRepository(EquipementS60::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S70':
                $equipment = $entityManager->getRepository(EquipementS70::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S80':
                $equipment = $entityManager->getRepository(EquipementS80::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S100':
                $equipment = $entityManager->getRepository(EquipementS100::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S120':
                $equipment = $entityManager->getRepository(EquipementS120::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
            
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                
                break;
            case 'S130':
                $equipment = $entityManager->getRepository(EquipementS130::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S140':
                $equipment = $entityManager->getRepository(EquipementS140::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S150':
                $equipment = $entityManager->getRepository(EquipementS150::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S160':
                $equipment = $entityManager->getRepository(EquipementS160::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            case 'S170':
                $equipment = $entityManager->getRepository(EquipementS170::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipmentOptimized($picturesArray, $entityManager, $equipment);
                break;
            
            default:
                break;
        }
        return $this->render('home/show-equipment-details.html.twig', [
            "equipment" => $equipment,
            "picturesData" => $picturesData,
        ]);
    }

}
