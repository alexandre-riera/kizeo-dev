<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\EquipementRepository;
use App\Entity\Equipement;


class EquipementController extends AbstractController
{
    #[Route('/api/equipements', name: 'app_equipement', methods: ['GET'])]
    public function getEquipements(EquipementRepository $equipementRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        // Equipements on Kizeo for every agence equipments list managed by EquipementRepository.php
        $equipementList  =  $equipementRepository->getEquipements();
        $jsonContactList = $serializer->serialize($equipementList, 'json');

        // Fetch all contacts in database
        $allEquipementsInDatabase = $entityManager->getRepository(Equipement::class)->findAll();
        
        // Persist each contact in database
        // Check before if contact exist in database
        if (count($allEquipementsInDatabase) !== count($equipementList)) {
            $equipementsArray = array_map(null, $equipementList);
            for ($i=0; $i < count($equipementsArray) ; $i++) {
                if (isset($equipementsArray[$i]) && !in_array($equipementsArray[$i],$allEquipementsInDatabase)) {
                    $equipment = new Equipement;
                    if (isset($equipementsArray[$i]['3'])) {
                        $equipment->setNumeroEquipement($equipementsArray[$i]['3']);
                    }else{
                        $equipment->setNumeroEquipement("");
                    }
                    if (isset($equipementsArray[$i]['4'])) {
                        $equipment->setNature($equipementsArray[$i]['4']);
                    }else{
                        $equipment->setNature("");
                    }
                    if (isset($equipementsArray[$i]['6'])) {
                        $equipment->setMiseEnService($equipementsArray[$i]['6']);
                    }else{
                        $equipment->setMiseEnService("");
                    }
                    if (isset($equipementsArray[$i]['8'])) {
                        $equipment->setNumeroDeSerie($equipementsArray[$i]['8']);
                    }else{
                        $equipment->setNumeroDeSerie("");
                    }
                    if (isset($equipementsArray[$i]['10'])) {
                        $equipment->setMarque($equipementsArray[$i]['10']);
                    }else{
                        $equipment->setMarque("");
                    }
                    if (isset($equipementsArray[$i]['16'])){
                        $equipment->setRepereSiteClient($equipementsArray[$i]['16']);
                    }else{
                        $equipment->setRepereSiteClient("");
                    }
                    if (isset($equipementsArray[$i]['18'])){
                        $equipment->setIdContact($equipementsArray[$i]['18']);
                    }else{
                        $equipment->setIdContact("");
                    }
                    if (isset($equipementsArray[$i]['20'])){
                        $equipment->setCodeSociete($equipementsArray[$i]['20']);
                    }else{
                        $equipment->setCodeSociete("");
                    }
                    if (isset($equipementsArray[$i]['22'])){
                        $equipment->setCodeAgence($equipementsArray[$i]['22']);
                    }else{
                        $equipment->setCodeAgence("");
                    }

                    // tell Doctrine you want to (eventually) save the Product (no queries yet)
                    $entityManager->persist($equipment);

                }
            }
            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
        }


        return new JsonResponse("Equipements sur API KIZEO : " . count($equipementList), Response::HTTP_OK, [], true);
    }
    
    #[Route('/api/equipements/grenoble', name: 'app_equipement_grenoble', methods: ['GET'])]
    public function getEquipementsGrenoble(EquipementRepository $equipementRepository): JsonResponse
    {
        // Equipements on Kizeo for every agence equipments list managed by EquipementRepository.php
        $equipementsGrenoble  =  $equipementRepository->getEquipementsGrenoble();

        
        return new JsonResponse($equipementsGrenoble, Response::HTTP_OK, [], true);
    }

// ---------------------------------------------- ROUTE POUR LES PORTAILS NE SERVANT PAS POUR L'INSTANT --------------------------------------
    // #[Route('/api/portails', name: 'app_portails', methods: ['GET'])]
    // public function getPortails(EquipementRepository $equipementRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    // {
    //     // Equipements on Kizeo for every agence equipments list managed by EquipementRepository.php
    //     $portailList  =  $equipementRepository->getPortails();
    //     $jsonContactList = $serializer->serialize($portailList, 'json');
    //     dd($portailList);
    //     // Fetch all contacts in database
    //     // $allPortailsInDatabase = $entityManager->getRepository(Equipement::class)->findAll();
        
    //     // Persist each contact in database
    //     // Check before if contact exist in database
    //     // if (count($allPortailsInDatabase) !== count($portailList)) {
    //     //     $portailsArray = array_map(null, $portailList);
    //     //     for ($i=0; $i < count($portailsArray) ; $i++) {
    //     //         if (isset($portailsArray[$i]) && !in_array($portailsArray[$i],$allPortailsInDatabase)) {
    //     //             $portail = new Equipement;
    //     //             if (isset($portailsArray[$i]['3'])) {
    //     //                 $portail->setNumeroEquipement($portailsArray[$i]['3']);
    //     //             }else{
    //     //                 $portail->setNumeroEquipement("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['4'])) {
    //     //                 $portail->setNature($portailsArray[$i]['4']);
    //     //             }else{
    //     //                 $portail->setNature("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['6'])) {
    //     //                 $portail->setMiseEnService($portailsArray[$i]['6']);
    //     //             }else{
    //     //                 $portail->setMiseEnService("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['8'])) {
    //     //                 $portail->setNumeroDeSerie($portailsArray[$i]['8']);
    //     //             }else{
    //     //                 $portail->setNumeroDeSerie("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['10'])) {
    //     //                 $portail->setMarque($portailsArray[$i]['10']);
    //     //             }else{
    //     //                 $portail->setMarque("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['16'])){
    //     //                 $portail->setRepereSiteClient($portailsArray[$i]['16']);
    //     //             }else{
    //     //                 $portail->setRepereSiteClient("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['18'])){
    //     //                 $portail->setIdContact($portailsArray[$i]['18']);
    //     //             }else{
    //     //                 $portail->setIdContact("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['20'])){
    //     //                 $portail->setCodeSociete($portailsArray[$i]['20']);
    //     //             }else{
    //     //                 $portail->setCodeSociete("");
    //     //             }
    //     //             if (isset($portailsArray[$i]['22'])){
    //     //                 $portail->setCodeAgence($portailsArray[$i]['22']);
    //     //             }else{
    //     //                 $portail->setCodeAgence("");
    //     //             }

    //     //             // tell Doctrine you want to (eventually) save the Product (no queries yet)
    //     //             $entityManager->persist($portail);

    //     //         }
    //     //     }
    //     //     // actually executes the queries (i.e. the INSERT query)
    //     //     $entityManager->flush();
    //     // }


    //     return new JsonResponse("Equipements sur API KIZEO : " . count($portailList), Response::HTTP_OK, [], true);
    // }
}
