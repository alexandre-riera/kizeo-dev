<?php

namespace App\Controller;

use App\Repository\HomeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
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

class AjaxController extends AbstractController
{
    #[Route('/ajax/filter-equipment', name: 'ajax_filter_equipment')]
    public function ajaxFilterEquipment(Request $request, EntityManagerInterface $entityManager, HomeRepository $homeRepository)
{
    // Récupérer les paramètres
    $clientSelected = $request->query->get('clientSelected');
    $agenceSelected = $request->query->get('agenceSelected');
    $idClientSelected = $request->query->get('idClientSelected');
    $clientAnneeFilter = $request->query->get('clientAnneeFilter');
    $clientVisiteFilter = $request->query->get('clientVisiteFilter');
    
    // Récupérer les équipements du client
    switch ($agenceSelected) {
        case 'S10':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS10::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S40':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS40::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S50':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS50::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S60':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS60::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S70':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS70::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S80':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS80::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S100':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS100::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S120':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS120::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S130':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS130::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S140':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS140::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S150':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS150::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S160':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS160::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        case 'S170':
            $clientSelectedEquipments = $entityManager->getRepository(EquipementS170::class)
                ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
            break;
        // Autres cas pour d'autres agences...
    }
    
    // Appliquer les filtres
    $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
        $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
        return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
    });
    
    // Récupérer les fichiers pdf du client
    $dateArray = [];
    foreach($clientSelectedEquipmentsFiltered as $equipment){
        if(!in_array($equipment->getDerniereVisite(), $dateArray)){
            $dateArray[] = $equipment->getDerniereVisite();
        }
    }
    $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $clientVisiteFilter, $agenceSelected, $dateArray);

    // Rendre uniquement le tableau des équipements
    return $this->render('components/equipment_table.html.twig', [
        'clientSelectedEquipmentsFiltered' => $clientSelectedEquipmentsFiltered,
        'directoriesLists' => $directoriesLists
    ]);
}
    #[Route('/ajax/filter-equipment-kuehne', name: 'ajax_filter_equipment_kuehne')]
    public function ajaxFilterEquipmentKuehne(Request $request, EntityManagerInterface $entityManager, HomeRepository $homeRepository)
    {
        // Récupérer les paramètres
        $clientSelected = $request->query->get('clientSelected');
        $agenceSelected = $request->query->get('agenceSelected');
        $idClientSelected = $request->query->get('idClientSelected');
        $clientAnneeFilter = $request->query->get('clientAnneeFilter');
        $clientVisiteFilter = $request->query->get('clientVisiteFilter');
        
        // Récupérer les équipements du client
        switch ($agenceSelected) {
            case 'S10':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS10::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S40':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS40::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S50':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS50::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S60':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS60::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S70':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS70::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S80':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS80::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S100':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS100::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S120':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS120::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S130':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS130::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S140':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS140::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S150':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS150::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S160':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS160::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S170':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS170::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            // Autres cas pour d'autres agences...
        }
        
        // Appliquer les filtres
        $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
            $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
            return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
        });
        
        // Récupérer les fichiers pdf du client
        $dateArray = [];
        foreach($clientSelectedEquipmentsFiltered as $equipment){
            if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                $dateArray[] = $equipment->getDerniereVisite();
            }
        }
        $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $clientVisiteFilter, $agenceSelected, $dateArray);

        // Rendre uniquement le tableau des équipements
        return $this->render('components/equipment_table_kuehne.html.twig', [
            'clientSelectedEquipmentsFiltered' => $clientSelectedEquipmentsFiltered,
            'directoriesLists' => $directoriesLists
        ]);
    }
    #[Route('/ajax/filter-equipment-gls', name: 'ajax_filter_equipment_gls')]
    public function ajaxFilterEquipmentGls(Request $request, EntityManagerInterface $entityManager, HomeRepository $homeRepository)
    {
        // Récupérer les paramètres
        $clientSelected = $request->query->get('clientSelected');
        $agenceSelected = $request->query->get('agenceSelected');
        $idClientSelected = $request->query->get('idClientSelected');
        $clientAnneeFilter = $request->query->get('clientAnneeFilter');
        $clientVisiteFilter = $request->query->get('clientVisiteFilter');
        
        // Récupérer les équipements du client
        switch ($agenceSelected) {
            case 'S10':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS10::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S40':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS40::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S50':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS50::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S60':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS60::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S70':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS70::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S80':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS80::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S100':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS100::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S120':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS120::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S130':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS130::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S140':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS140::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S150':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS150::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S160':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS160::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            case 'S170':
                $clientSelectedEquipments = $entityManager->getRepository(EquipementS170::class)
                    ->findBy(['id_contact' => $idClientSelected], ['numero_equipement' => 'ASC']);
                break;
            // Autres cas pour d'autres agences...
        }
        
        // Appliquer les filtres
        $clientSelectedEquipmentsFiltered = array_filter($clientSelectedEquipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
            $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
            return ($annee_date_equipment == $clientAnneeFilter && $equipment->getVisite() == $clientVisiteFilter);
        });
        
        // Récupérer les fichiers pdf du client
        $dateArray = [];
        foreach($clientSelectedEquipmentsFiltered as $equipment){
            if(!in_array($equipment->getDerniereVisite(), $dateArray)){
                $dateArray[] = $equipment->getDerniereVisite();
            }
        }
        $directoriesLists = $homeRepository->getListOfPdf($clientSelected, $clientVisiteFilter, $agenceSelected, $dateArray);

        // Rendre uniquement le tableau des équipements
        return $this->render('components/equipment_table_gls.html.twig', [
            'clientSelectedEquipmentsFiltered' => $clientSelectedEquipmentsFiltered,
            'directoriesLists' => $directoriesLists
        ]);
    }
}
