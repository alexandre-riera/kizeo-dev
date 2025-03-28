<?php

namespace App\Controller;

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
use App\Repository\KuehneRepository;
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

class KuehneController extends AbstractController
{
    #[Route('/kuehne', name: 'app_kuehne')]
    public function index(CacheInterface $cache,EntityManagerInterface $entityManager, KuehneRepository $kuehneRepository, ContactsCCRepository $contactsCCRepository): Response
    {   
        // ---------------------------------------------------------------------- GET KUEHNE CONTACTS KIZEO BY AGENCY
        // IMPORTANT  Return $listClientsKuehneFromKizeo array filled with ContactsCC object structured with his id_contact, raison_sociale and code_agence
        $clientsKuehneStEtienne = $kuehneRepository->getListClientFromKizeoById(427441, $entityManager, $contactsCCRepository);
        $clientsKuehneGrenoble = $kuehneRepository->getListClientFromKizeoById(409466, $entityManager, $contactsCCRepository);
        $clientsKuehneLyon = $kuehneRepository->getListClientFromKizeoById(427443, $entityManager, $contactsCCRepository);
        $clientsKuehneParisNord = $kuehneRepository->getListClientFromKizeoById(421994, $entityManager, $contactsCCRepository);
        $clientsKuehneMontpellier = $kuehneRepository->getListClientFromKizeoById(423852, $entityManager, $contactsCCRepository);
        $clientsKuehneHautsDeFrance = $kuehneRepository->getListClientFromKizeoById(434249, $entityManager, $contactsCCRepository);
        $clientsKuehneEpinal = $kuehneRepository->getListClientFromKizeoById(427681, $entityManager, $contactsCCRepository);
        $clientsKuehneRouen = $kuehneRepository->getListClientFromKizeoById(427677, $entityManager, $contactsCCRepository);
        
        // ---------------------------------------------------------------------- GET KUEHNE CONTACTS GESTAN BY AGENCY
        $clientsKuehneGroup =  $cache->get('client_group', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900 ); // 15 minutes in cache
            $clients = $entityManager->getRepository(ContactS10::class)->findAll();
            return $clients;
        });
        $clientsKuehneBordeaux =  $cache->get('client_bordeaux', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900 ); // 15 minutes in cache
            $clients = $entityManager->getRepository(ContactS70::class)->findAll();
            return $clients;
        });
        $clientsKuehneToulouse =  $cache->get('client_toulouse', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900 ); // 15 minutes in cache
            $clients = $entityManager->getRepository(ContactS130::class)->findAll();
            return $clients;
        });
        $clientsKuehnePaca =  $cache->get('client_paca', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900 ); // 15 minutes in cache
            $clients = $entityManager->getRepository(ContactS150::class)->findAll();
            return $clients;
        });
        $clientsKuehneRennes =  $cache->get('client_rennes', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900 ); // 15 minutes in cache
            $clients = $entityManager->getRepository(ContactS170::class)->findAll();
            return $clients;
        });
        
        $agenciesArray =  $cache->get('agency_array', function (ItemInterface $item) use ($entityManager)  {
            $item->expiresAfter(900 ); // 15 minutes in cache
            $agencies = $entityManager->getRepository(Agency::class)->findAll();
            return $agencies;
        });

        // Merge all contacts arrays
        $allKuehneContactsFromFrance = array_merge($clientsKuehneStEtienne, $clientsKuehneGrenoble, $clientsKuehneLyon, $clientsKuehneParisNord, $clientsKuehneMontpellier, $clientsKuehneHautsDeFrance, $clientsKuehneEpinal, $clientsKuehneRouen);

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
                foreach ($allKuehneContactsFromFrance as $kuehneContact) {
                    if ($clientSelected == $kuehneContact->raison_sociale) {
                        $agenceSelected = $kuehneContact->code_agence;
                        $idClientSelected = $kuehneContact->id_contact;
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
        
        dump($agenceSelected);
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
            'clientsGroup' => $clientsKuehneGroup,  // Array of Contacts
            'clientsStEtienne' => $clientsKuehneStEtienne,  // Array of Contacts
            'clientsGrenoble' => $clientsKuehneGrenoble,  // Array of Contacts
            'clientsLyon' => $clientsKuehneLyon,  // Array of Contacts
            'clientsBordeaux' => $clientsKuehneBordeaux,  // Array of Contacts
            'clientsParisNord' => $clientsKuehneParisNord,  // Array of Contacts
            'clientsMontpellier' => $clientsKuehneMontpellier,  // Array of Contacts
            'clientsHautsDeFrance' => $clientsKuehneHautsDeFrance,  // Array of Contacts
            'clientsToulouse' => $clientsKuehneToulouse,  // Array of Contacts
            'clientsEpinal' => $clientsKuehneEpinal,  // Array of Contacts
            'clientsPaca' => $clientsKuehnePaca,  // Array of Contacts
            'clientsRouen' => $clientsKuehneRouen,  // Array of Contacts
            'clientsRennes' => $clientsKuehneRennes,  // Array of Contacts
            'clientSelected' => $clientSelected, // String
            'agenceSelected' => $agenceSelected, // String
            'agenciesArray' => $agenciesArray, // Array of all agencies (params : code, agence)
            'clientSelectedInformations'  => $clientSelectedInformations, // Selected Entity Contact
            'clientSelectedEquipmentsFiltered'  => $clientSelectedEquipmentsFiltered, // Selected Entity Equipement where last visit is superior 3 months ago
            'totalClientSelectedEquipmentsFiltered'  => count($clientSelectedEquipmentsFiltered), // Total Selected Entity Equipement where last visit is superior 3 months ago
            'directoriesLists' => $directoriesLists, // Array with Objects $myFile with path and annee properties in it
            'visiteDuClient' =>  $visiteDuClient,
            'idClientSelected' =>  $idClientSelected,
            'allKuehneContactsFromFrance' =>  $allKuehneContactsFromFrance,
        ]);
    }

    #[Route("/kuehne/upload/file", name:"kuehne_upload_file")]
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
        return $this->redirectToRoute('app_kuehne');
    }
}
