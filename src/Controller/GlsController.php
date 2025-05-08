<?php

namespace App\Controller;

use DateTime;
use App\Entity\Agency;
use App\Entity\FilesCC;
use App\Entity\ContactS10;
use App\Entity\ContactS40;
use App\Entity\ContactS50;
use App\Entity\ContactS60;
use App\Entity\ContactS70;
use App\Entity\ContactS80;
use App\Entity\ContactsCC;
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
use App\Repository\GlsRepository;
use App\Repository\ContactsCCRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseIsSuccessful;

final class GlsController extends AbstractController
{
    #[Route('/gls', name: 'app_gls')]
    public function index(CacheInterface $cache,EntityManagerInterface $entityManager, GlsRepository $glsRepository, ContactsCCRepository $contactsCCRepository, Request $request): Response
    {   
        // ---------------------------------------------------------------------- GET GLS CONTACTS KIZEO BY AGENCY
        // IMPORTANT  Return $listClientsGlsFromKizeo array filled with ContactsCC object structured with his id_contact, raison_sociale and code_agence
        $clientsGlsGroup = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_GROUP'], $entityManager, $contactsCCRepository);
        $clientsGlsStEtienne = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_ST_ETIENNE'], $entityManager, $contactsCCRepository);
        $clientsGlsGrenoble = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_GRENOBLE'], $entityManager, $contactsCCRepository);
        $clientsGlsLyon = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_LYON'], $entityManager, $contactsCCRepository);
        $clientsGlsBordeaux = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_BORDEAUX'], $entityManager, $contactsCCRepository);
        $clientsGlsParisNord = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_PARIS_NORD'], $entityManager, $contactsCCRepository);
        $clientsGlsMontpellier = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_MONTPELLIER'], $entityManager, $contactsCCRepository);
        $clientsGlsHautsDeFrance = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_HAUTS_DE_FRANCE'], $entityManager, $contactsCCRepository);
        $clientsGlsToulouse = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_TOULOUSE'], $entityManager, $contactsCCRepository);
        $clientsGlsEpinal = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_EPINAL'], $entityManager, $contactsCCRepository);
        $clientsGlsPaca = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_PACA'], $entityManager, $contactsCCRepository);
        $clientsGlsRouen = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_ROUEN'], $entityManager, $contactsCCRepository);
        // $clientsGlsRennes = $glsRepository->getListClientFromKizeoById($_ENV['PROD_CLIENTS_RENNES'], $entityManager, $contactsCCRepository);  

        // Merge all contacts arrays
        $allGlsContactsFromFrance = array_merge($clientsGlsGroup, $clientsGlsStEtienne, $clientsGlsGrenoble, $clientsGlsLyon, $clientsGlsBordeaux, $clientsGlsParisNord, $clientsGlsMontpellier, $clientsGlsHautsDeFrance, $clientsGlsToulouse, $clientsGlsEpinal, $clientsGlsPaca, $clientsGlsRouen);
        // GET CLIENT SELECTED INFORMATION BY AGENCY BY HIS RAISON_SOCIALE
        $clientSelectedInformations  = "";
        // GET CLIENT SELECTED EQUIPMENTS BY AGENCY BY HIS ID_CONTACT
        $clientSelectedEquipments  = [];
        $clientSelectedEquipmentsFiltered = [];
        // GET VALUE OF AGENCY SELECTED
        $agenceSelected = "";
        // // GET VALUE OF CLIENT SELECTED
        $clientSelected = "";
        // GET directories and files OF CLIENT SELECTED
        $directoriesLists = [];

        $idClientSelected ="";
        // Récupération du client sélectionné et SET de $agenceSelected et $idClientSlected
        if(isset($_POST['submitClient'])){  
            if(!empty($_POST['clientName'])) {  
                $clientSelected = $_POST['clientName'];
                foreach ($allGlsContactsFromFrance as $glsContact) {
                    if ($clientSelected == $glsContact->raison_sociale) {
                        $agenceSelected = $glsContact->code_agence;
                        $idClientSelected = $glsContact->id_contact;
                    }
                }
            } else {  
                echo 'Please select the value.';
            }  
        }
        // Récupération du fichier sélectionné 
        // if(isset($_POST['submitFile'])){  
        //     if(!empty($_POST['fileselected'])) {  
        //         $fileSelected = $_POST['fileselected'];
        //         dd($fileSelected);
                
        //     } else {  
        //         echo 'Please select the value.';
        //     }  
        // }
        
        // // ENLEVER LE NOM DE L'AGENCE ET L'ESPACE A LA FIN DU NOM DU CLIENT SÉLECTIONNÉ
        // $clientSelectedRTrimmed = rtrim($clientSelected, "\S10\S40\S50\S60\S70\S80\S100\S120\S130\S140\S150\S160\S170\ \-");
        // $clientSelectedSplitted = preg_split("/[-]/",$clientSelectedRTrimmed);
        // $idClientSelected = $clientSelectedSplitted[0];
        // foreach ($clientSelectedSplitted as $key) {
        //     $clientSelected = $key;
        // }
        
        $visiteDuClient = "";
        if ($clientSelected != NULL) {
            switch ($agenceSelected) {
                case 'S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Vérifier si une date a été trouvée
                    if ($absoluteLatestVisitDate !== null) {
                        // Calculer la date limite inférieure (2 mois avant la date la plus récente)
                        $twoMonthsAgo = clone $absoluteLatestVisitDate;
                        $twoMonthsAgo->modify('-2 months');
                        $twoMonthsAgo = $twoMonthsAgo->format('Y-m-d');
                    } else {
                        // Gérer le cas où aucune date n'a été trouvée
                        $twoMonthsAgo = null;
                    }

                    // Filtrer les équipements dans l'intervalle
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($absoluteLatestVisitDate, $twoMonthsAgo) {
                        $equipmentDate = $equipment->getDerniereVisite();
                        
                        return $equipmentDate !== null && 
                            $equipmentDate <= $absoluteLatestVisitDate && 
                            $equipmentDate >= $twoMonthsAgo;
                    });
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    $currentVisit = "";
                    if (isset($clientSelectedEquipmentsFiltered[1])) {
                        $currentVisit = $clientSelectedEquipmentsFiltered[1]->getVisite();
                    }else if (isset($clientSelectedEquipmentsFiltered[0])){
                        $currentVisit = $clientSelectedEquipmentsFiltered[0]->getVisite();
                    }else {
                        $currentVisit = null;
                    }
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $currentVisit, $agenceSelected, $dateArray);
                    break;
                case ' S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case 'S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                case ' S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    $dateArray = [];
                    // Trouver la date de visite la plus récente
                    $absoluteLatestVisitDate = null;
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDerniereVisite() !== null) {
                            $currentDate = new DateTime($equipment->getDerniereVisite());
                            
                            // Comparer et garder la date la plus récente
                            if ($absoluteLatestVisitDate === null || $currentDate > $absoluteLatestVisitDate) {
                                $absoluteLatestVisitDate = $currentDate;
                            }
                        }
                    }

                    // Déterminer l'année et la visite par défaut
                    $defaultYear = $absoluteLatestVisitDate ? $absoluteLatestVisitDate->format('Y') : '';
                    $defaultVisit = "";

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

                    // Récupérer les filtres depuis la requête ou utiliser les valeurs par défaut
                    $clientAnneeFilter = $request->query->get('clientAnneeFilter', $defaultYear);
                    $clientVisiteFilter = $request->query->get('clientVisiteFilter', $defaultVisit);

                    // Filtrer les équipements par défaut avec les valeurs de la dernière visite
                    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                    });

                    // Si aucun équipement n'est trouvé avec les filtres par défaut, montrer tous les équipements
                    if (empty($clientSelectedEquipmentsFiltered)) {
                        $clientSelectedEquipmentsFiltered = $clientSelectedEquipments;
                    }
                    foreach($clientSelectedEquipmentsFiltered as $equipment){
                        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                            $dateArray[] = $equipment->getDerniereVisite();
                        }
                    }
                    // $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected, $dateArray);
                    $directoriesLists = $glsRepository->getListOfPdf($clientSelected, $defaultVisit, $agenceSelected);
                    break;
                
                default:
                    break;
            }
        }
        $agenceSelected = trim($agenceSelected);

        $clientAnneeFilterArray = []; // Je filtre les résultats des filtres d'année
        foreach ($clientSelectedEquipments as $equipment) {
            $date_equipment = date("Y", strtotime($equipment->getDateEnregistrement()));
            // $date_equipment = $equipment->getDateEnregistrement();
            if (!in_array($date_equipment, $clientAnneeFilterArray)) {
                $clientAnneeFilterArray [] = $date_equipment;
            }
        }
        $clientVisiteFilterArray = []; // Je filtre les résultats des filtres de visite
        foreach ($clientSelectedEquipments as $equipment) {
            $visite_equipment = $equipment->getVisite();
            if (!in_array($visite_equipment, $clientVisiteFilterArray)) {
                $clientVisiteFilterArray [] = $visite_equipment;
            }
        }
        $clientAnneeFilter = "";
        $clientVisiteFilter = "";
        // Récupération des filtres via la requête
        if ($request->query->get('submitFilters')) {
            $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
            $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
            $clientSelectedEquipmentsFiltered = $clientSelectedEquipments; // Initialisez avec tous les équipements

            // Validation des filtres
            if (empty($clientAnneeFilter)) {
                $this->addFlash('error', 'Sélectionnez l\'année.');
            }

            if (empty($clientVisiteFilter)) {
                $this->addFlash('error', 'Sélectionnez la visite.');
            }

            // Filtrage des équipements
            if (!empty($clientAnneeFilter) && !empty($clientVisiteFilter)) {
                $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                    $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                    return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
                });
            }
        }

        return $this->render('gls/index.html.twig', [
            'clientsGroup' => $clientsGlsGroup,  // Array of Contacts
            'clientsStEtienne' => $clientsGlsStEtienne,  // Array of Contacts
            'clientsGrenoble' => $clientsGlsGrenoble,  // Array of Contacts
            'clientsLyon' => $clientsGlsLyon,  // Array of Contacts
            'clientsBordeaux' => $clientsGlsBordeaux,  // Array of Contacts
            'clientsParisNord' => $clientsGlsParisNord,  // Array of Contacts
            'clientsMontpellier' => $clientsGlsMontpellier,  // Array of Contacts
            'clientsHautsDeFrance' => $clientsGlsHautsDeFrance,  // Array of Contacts
            'clientsToulouse' => $clientsGlsToulouse,  // Array of Contacts
            'clientsEpinal' => $clientsGlsEpinal,  // Array of Contacts
            'clientsPaca' => $clientsGlsPaca,  // Array of Contacts
            'clientsRouen' => $clientsGlsRouen,  // Array of Contacts
            // 'clientsRennes' => $clientsGlsRennes,  // Array of Contacts
            'clientSelected' => $clientSelected, // String
            'agenceSelected' => $agenceSelected, // String
            // 'agenciesArray' => $agenciesArray, // Array of all agencies (params : code, agence)
            'clientSelectedInformations'  => $clientSelectedInformations, // Selected Entity Contact
            'clientSelectedEquipmentsFiltered'  => $clientSelectedEquipmentsFiltered, // Selected Entity Equipement where last visit is superior 3 months ago
            'totalClientSelectedEquipmentsFiltered'  => count($clientSelectedEquipmentsFiltered), // Total Selected Entity Equipement where last visit is superior 3 months ago
            'directoriesLists' => $directoriesLists, // Array with Objects $myFile with path and annee properties in it
            'visiteDuClient' =>  $visiteDuClient,
            'idClientSelected' =>  $idClientSelected,
            'allGlsContactsFromFrance' =>  $allGlsContactsFromFrance,
            'clientAnneeFilterArray' =>  $clientAnneeFilterArray,
            'clientAnneeFilter' =>  $clientAnneeFilter,
            'clientVisiteFilterArray' =>  $clientVisiteFilterArray,
            'clientVisiteFilter' =>  $clientVisiteFilter,
        ]);
    }

    #[Route("/gls/upload/file", name:"gls_upload_file")]
    public function temporaryUploadAction(Request $request, EntityManagerInterface $entityManager, ContactsCCRepository $contactsCCRepository) : Response
    {
        // Récupération du client sélectionné et SET de $agenceSelected et $idClientSlected
        if(isset($_POST['submitFile'])){  
            if(!empty($_POST['id_client']) && !empty($_POST['client_name'])) {
                /** @var UploadedFile $uploadedFile */
                $uploadedFile = $request->files->get('fileselected');
                $destination = $this->getParameter('kernel.project_dir').'/public/uploads/documents_cc/'. $_POST['client_name'];
                $uploadedFile->move($destination, $uploadedFile->getClientOriginalName());
                // Fetch the Contact entity
                $contact = $contactsCCRepository->findOneBy(array('id_contact' => $_POST['id_client']));
                // Create a new FileCC in BDD
                $fileCC = new FilesCC();
                $fileCC->setName($uploadedFile->getClientOriginalName());
                $fileCC->setPath($this->getParameter('kernel.project_dir').'/public/uploads/documents_cc/'. $_POST['client_name']);
                $fileCC->setIdContactCc($contact);
                $entityManager->persist($fileCC);
                $entityManager->flush();
                echo 'Le fichier a été téléchargé avec succès.';
            } else {  
                echo 'Merci de sélectionner un fichier';
                
            }  
        }
        return $this->redirectToRoute('app_gls');
    }
}
