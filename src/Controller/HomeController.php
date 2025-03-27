<?php

namespace App\Controller;

use DateTime;
use DOMDocument;
use DateInterval;
use App\Entity\Form;
use App\Entity\Agency;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{

    #[Route('/', name: 'app_front')]
    public function index(CacheInterface $cache,EntityManagerInterface $entityManager, SerializerInterface $serializer, Request $request, HomeRepository $homeRepository): Response
    {
        // GET CONTACTS KIZEO BY AGENCY
        
        // Problème avec le cache qui sert toujours les entité de Contact Group à la place de la liste Kizeo
        $clientsGroup = $homeRepository->getListClientFromKizeoById($_ENV["TEST_CLIENTS_GROUP"]);
        // $clientsGroup  =  $cache->get('client_group', function (ItemInterface $item) use ($homeRepository)  {
        //     $item->expiresAfter(900); // 15 minutes in cache
        //     $clients = $homeRepository->getListClientFromKizeoById($_ENV["TEST_CLIENTS_GROUP"]);
        //     return $clients;
        // });
        $clientsStEtienne  =  $cache->get('client_st_etienne', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_ST_ETIENNE"]);
            return $clients;
        });
        $clientsGrenoble  =  $cache->get('client_grenoble', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_GRENOBLE"]);
            return $clients;
        });
        $clientsLyon  =  $cache->get('client_lyon', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_LYON"]);
            return $clients;
        });
        // Problème avec le cache qui sert toujours les entité de Contact Bordeaux à la place de la liste Kizeo
        $clientsBordeaux = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_BORDEAUX"]);
        // $clientsBordeaux  =  $cache->get('client_bordeaux', function (ItemInterface $item) use ($homeRepository)  {
        //     $item->expiresAfter(900); // 15 minutes in cache
        //     $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_BORDEAUX"]);
        //     return $clients;
        // });
        $clientsParisNord  =  $cache->get('client_paris_nord', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_PARIS_NORD"]);
            return $clients;
        });
        $clientsMontpellier =  $cache->get('client_montpellier', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_MONTPELLIER"]);
            return $clients;
        });
        // Problème avec le cache qui sert toujours les entité de Contact clientsHautsDeFrance à la place de la liste Kizeo
        $clientsHautsDeFrance = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_HAUTS_DE_FRANCE"]);
        // $clientsHautsDeFrance =  $cache->get('client_hauts_de_france', function (ItemInterface $item) use ($homeRepository)  {
        //     $item->expiresAfter(900); // 15 minutes in cache
        //     $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_HAUTS_DE_FRANCE"]);
        //     return $clients;
        // });
        // Problème avec le cache qui sert toujours les entité de Contact Bordeaux à la place de la liste Kizeo
        $clientsToulouse = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_TOULOUSE"]);
        // $clientsToulouse =  $cache->get('client_toulouse', function (ItemInterface $item) use ($homeRepository)  {
        //     $item->expiresAfter(900); // 15 minutes in cache
        //     $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_TOULOUSE"]);
        //     return $clients;
        // });
        $clientsEpinal =  $cache->get('client_epinal', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_EPINAL"]);
            return $clients;
        });
        // Problème avec le cache qui sert toujours les entité de Contact Bordeaux à la place de la liste Kizeo
        $clientsPaca = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_PACA"]);
        // $clientsPaca =  $cache->get('client_paca', function (ItemInterface $item) use ($homeRepository)  {
        //     $item->expiresAfter(900); // 15 minutes in cache
        //     $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_PACA"]);
        //     return $clients;
        // });
        $clientsRouen =  $cache->get('client_rouen', function (ItemInterface $item) use ($homeRepository)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $clients = $homeRepository->getListClientFromKizeoById($_ENV["PROD_CLIENTS_ROUEN"]);
            return $clients;
        });
        // Problème avec le cache qui sert toujours les entité de Contact Rennes à la place de la liste Kizeo
        $clientsRennes = $homeRepository->getListClientFromKizeoById($_ENV["TEST_CLIENTS_RENNES"]);
        // $clientsRennes =  $cache->get('client_rennes', function (ItemInterface $item) use ($homeRepository)  {
        //     $item->expiresAfter(900); // 15 minutes in cache
        //     $clients = $homeRepository->getListClientFromKizeoById($_ENV["TEST_CLIENTS_RENNES"]);
        //     return $clients;
        // });
        
        // GET AGENCIES FROM DATABASE
        $agenciesArray =  $cache->get('agency_array', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900); // 15 minutes in cache
            $agencies = $entityManager->getRepository(Agency::class)->findAll();
            return $agencies;
        });

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

        // Récupération de l'agence sélectionnée nécessaire pour charger la liste client de l'agence
        if(isset($_POST['submitAgence'])){  
            if(!empty($_POST['agenceName'])) {
                $agenceSelected = $_POST['agenceName'];
            } else {  
                echo 'Please select the value.';
            }  
        }
        // Récupération du client sélectionné et SET de $agenceSelected par les 4 derniers caractères de $clientSelected
        if(isset($_POST['submitClient'])){  
            if(!empty($_POST['clientName'])) {  
                $clientSelected = $_POST['clientName'];
                $agenceSelected = substr($clientSelected, -4);
            } else {  
                echo 'Please select the value.';
            }  
        }
        // Mettre des dump ici pour agence selectionnée et client selectionné si besoin
        dump($agenceSelected);
        
        // ENLEVER LE NOM DE L'AGENCE ET L'ESPACE A LA FIN DU NOM DU CLIENT SÉLECTIONNÉ
        $clientSelectedRTrimmed = rtrim($clientSelected, "\S10\S40\S50\S60\S70\S80\S100\S120\S130\S140\S150\S160\S170\ \-");
        $clientSelectedSplitted = preg_split("/[-]/",$clientSelectedRTrimmed);
        $idClientSelected = $clientSelectedSplitted[0];
        foreach ($clientSelectedSplitted as $key) {
            $clientSelected = $key;
        }
        $idClientSelected = rtrim($idClientSelected, "\ ");
        dump($idClientSelected);
        dump($clientSelected);
        $visiteDuClient = "";
        if ($clientSelected != NULL) {
            switch ($agenceSelected) {
                case 'S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S10':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);

                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S40':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    // PUT HERE THE FUNCTION TO GET CLIENTSELECTED PDF
                    break;
                case ' S50':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S70':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S80':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S100':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S120':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S130':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S140':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S150':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S160':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case 'S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                case ' S170':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                
                default:
                    break;
            }
        }
        $agenceSelected = trim($agenceSelected);
        dump($clientSelectedEquipmentsFiltered);
        return $this->render('home/index.html.twig', [
            'clientsGroup' => $clientsGroup,  // Array of Contacts
            'clientsStEtienne' => $clientsStEtienne,  // Array of Contacts
            'clientsGrenoble' => $clientsGrenoble,  // Array of Contacts
            'clientsLyon' => $clientsLyon,  // Array of Contacts
            'clientsBordeaux' => $clientsBordeaux,  // Array of Contacts
            'clientsParisNord' => $clientsParisNord,  // Array of Contacts
            'clientsMontpellier' => $clientsMontpellier,  // Array of Contacts
            'clientsHautsDeFrance' => $clientsHautsDeFrance,  // Array of Contacts
            'clientsToulouse' => $clientsToulouse,  // Array of Contacts
            'clientsEpinal' => $clientsEpinal,  // Array of Contacts
            'clientsPaca' => $clientsPaca,  // Array of Contacts
            'clientsRouen' => $clientsRouen,  // Array of Contacts
            'clientsRennes' => $clientsRennes,  // Array of Contacts
            'clientSelected' => $clientSelected, // String
            'agenceSelected' => $agenceSelected, // String
            'agenciesArray' => $agenciesArray, // Array of all agencies (params : code, agence)
            'clientSelectedInformations'  => $clientSelectedInformations, // Selected Entity Contact
            'clientSelectedEquipmentsFiltered'  => $clientSelectedEquipmentsFiltered, // Selected Entity Equipement where last visit is superior 3 months ago
            'totalClientSelectedEquipmentsFiltered'  => count($clientSelectedEquipmentsFiltered), // Total Selected Entity Equipement where last visit is superior 3 months ago
            'directoriesLists' => $directoriesLists, // Array with Objects $myFile with path and annee properties in it
            'visiteDuClient' =>  $visiteDuClient,
            'idClientSelected' =>  $idClientSelected,
        ]);
    }

    #[Route('/save/modal/equipement', name: 'app_save_modal_equipement')]
    public function saveModalInDatabase(EntityManagerInterface $entityManager){

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
            $equipement = new $entityAgency;
            $equipement->setIdContact($equipmentIdContact);
            $equipement->setDateEnregistrement(date("Y-m-d H:i:s"));
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
        // return new Response("L'équipement édité dans la modal a bien été enregistré en base de données", Response::HTTP_OK, [], true);
        return $this->redirectToRoute('app_front');
    }

    #[Route('/show/equipement/details/{agence}/{id}', name: 'app_show_equipement_details_by_id')]
    public function showEquipmentDetailsById(string $agence, string $id, EntityManagerInterface $entityManager){
        switch ($agence) {
            case 'S10':
                $equipment = $entityManager->getRepository(EquipementS10::class)->findOneBy(['id' => $id]); // L'ID remonté est bon 
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S40':
                $equipment = $entityManager->getRepository(EquipementS40::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S50':
                $equipment = $entityManager->getRepository(EquipementS50::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S60':
                $equipment = $entityManager->getRepository(EquipementS60::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S70':
                $equipment = $entityManager->getRepository(EquipementS70::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S80':
                $equipment = $entityManager->getRepository(EquipementS80::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S100':
                $equipment = $entityManager->getRepository(EquipementS100::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S120':
                $equipment = $entityManager->getRepository(EquipementS120::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S130':
                $equipment = $entityManager->getRepository(EquipementS130::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S140':
                $equipment = $entityManager->getRepository(EquipementS140::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S150':
                $equipment = $entityManager->getRepository(EquipementS150::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S160':
                $equipment = $entityManager->getRepository(EquipementS160::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            case 'S170':
                $equipment = $entityManager->getRepository(EquipementS170::class)->findOneBy(['id' => $id]); // L'ID remonté est bon
                $picturesArray = $entityManager->getRepository(Form::class)->findBy(array('code_equipement' => $equipment->getNumeroEquipement(), 'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()));
                $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
                break;
            
            default:
                dump($agence . " est vide ou id equipment est vide");
                break;
        }
        return $this->render('home/show-equipment-details.html.twig', [
            "equipment" => $equipment,
            "picturesData" => $picturesData,
        ]);
    }
}
