<?php

namespace App\Controller;

use App\Entity\Agency;
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
use App\Repository\KuehneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class KuehneController extends AbstractController
{
    #[Route('/kuehne', name: 'app_kuehne')]
    public function index(CacheInterface $cache,EntityManagerInterface $entityManager, KuehneRepository $kuehneRepository): Response
    {
        // GET CONTACTS KIZEO BY AGENCY
        // $clientsGroup  =  $kuehneRepository->getListClientFromKizeoById();
        
        $clientsStEtienne  =  $cache->get('client_st_etienne', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(427441);
            return $clients;
        });
        $clientsGrenoble  =  $cache->get('client_grenoble', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(409466);
            return $clients;
        });
        $clientsLyon  =  $cache->get('client_lyon', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(427443);
            return $clients;
        });
        // $clientsBordeaux  =  $kuehneRepository->getListClientFromKizeoById();
        $clientsParisNord  =  $cache->get('client_paris_nord', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(421994);
            return $clients;
        });
        $clientsMontpellier =  $cache->get('client_montpellier', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(423852);
            return $clients;
        });
        $clientsHautsDeFrance =  $cache->get('client_hauts_de_france', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(434249);
            return $clients;
        });
        // $clientsHautsDeFrance  =  $kuehneRepository->getListClientFromKizeoById();
        // $clientsToulouse  =  $kuehneRepository->getListClientFromKizeoById();
        $clientsEpinal =  $cache->get('client_epinal', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(427681);
            return $clients;
        });
        // $clientsPaca  =  $kuehneRepository->getListClientFromKizeoById();
        $clientsRouen =  $cache->get('client_rouen', function (ItemInterface $item) use ($kuehneRepository)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $kuehneRepository->getListClientFromKizeoById(427677);
            return $clients;
        });
        // $clientsRennes  =  $kuehneRepository->getListClientFromKizeoById();
        
        // ---------------------       GET CONTACTS GESTAN BY AGENCY
        $clientsGroup =  $cache->get('client_group', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $entityManager->getRepository(ContactS10::class)->findAll();
            return $clients;
        });
        // $clientsStEtienne  =  $entityManager->getRepository(ContactS40::class)->findAll();
        // $clientsGrenoble  =  $entityManager->getRepository(ContactS50::class)->findAll();
        // $clientsLyon  =  $entityManager->getRepository(ContactS60::class)->findAll();
        $clientsBordeaux =  $cache->get('client_bordeaux', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $entityManager->getRepository(ContactS70::class)->findAll();
            return $clients;
        });
        // $clientsParisNord  =  $entityManager->getRepository(ContactS80::class)->findAll();
        // $clientsMontpellier  =  $entityManager->getRepository(ContactS100::class)->findAll();
        // $clientsHautsDeFrance =  $cache->get('client_hauts_de_france', function (ItemInterface $item) use ($entityManager)  {
        //     $item->expiresAfter(2629800 ); // 1 month in cache
        //     $clients = $entityManager->getRepository(ContactS120::class)->findAll();
        //     return $clients;
        // });
        $clientsToulouse =  $cache->get('client_toulouse', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $entityManager->getRepository(ContactS130::class)->findAll();
            return $clients;
        });
        // $clientsEpinal  =  $entityManager->getRepository(ContactS140::class)->findAll();
        $clientsPaca =  $cache->get('client_paca', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $entityManager->getRepository(ContactS150::class)->findAll();
            return $clients;
        });
        // $clientsRouen  =  $entityManager->getRepository(ContactS160::class)->findAll();
        $clientsRennes =  $cache->get('client_rennes', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
            $clients = $entityManager->getRepository(ContactS170::class)->findAll();
            return $clients;
        });
        
        $agenciesArray =  $cache->get('agency_array', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(2629800 ); // 1 month in cache
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    // PUT HERE THE FUNCTION TO GET CLIENTSELECTED PDF
                    break;
                case 'S60':
                    $clientSelectedInformations  =  $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $idClientSelected]);
                    $clientSelectedEquipments  = $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                    
                    
                    foreach ($clientSelectedEquipments as $equipment) {
                        if ($equipment->getDateEnregistrement() != NULL) {
                            array_push($clientSelectedEquipmentsFiltered, $equipment);
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
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
                            $directoriesLists = $kuehneRepository->getListOfPdf($clientSelected, $equipment->getVisite(), $agenceSelected);
                            $visiteDuClient =  $equipment->getVisite();
                        }
                    }
                    break;
                
                default:
                    break;
            }
        }
        $agenceSelected = trim($agenceSelected);

        return $this->render('kuehne/index.html.twig', [
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
}
