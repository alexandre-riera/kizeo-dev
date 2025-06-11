<?php
// src/Controller/EquipementPdfController.php
namespace App\Controller;

use App\Entity\Form;
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
use App\Service\PdfGenerator;
use App\Entity\EquipementS100;
use App\Entity\EquipementS120;
use App\Entity\EquipementS130;
use App\Entity\EquipementS140;
use App\Entity\EquipementS150;
use App\Entity\EquipementS160;
use App\Entity\EquipementS170;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EquipementPdfController extends AbstractController
{
    private $pdfGenerator;
    
    public function __construct(PdfGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
    }
    
    /**
     * 
     */
    #[Route('/equipement/pdf/{agence}/{id}', name: 'equipement_pdf_single')]
    public function generateSingleEquipementPdf(string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'équipement selon l'agence (même logique que votre fonction existante)
        $equipment = $this->getEquipmentByAgence($agence, $id, $entityManager);
        
        if (!$equipment) {
            throw $this->createNotFoundException('Équipement non trouvé');
        }
        
        // Récupérer les photos selon votre logique existante
        $picturesArray = $entityManager->getRepository(Form::class)->findBy([
            'code_equipement' => $equipment->getNumeroEquipement(), 
            'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
        ]);
        
        $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
        
        // Générer le HTML pour le PDF
        $html = $this->renderView('pdf/single_equipement.html.twig', [
            'equipment' => $equipment,
            'picturesData' => $picturesData,
            'agence' => $agence
        ]);
        
        // Générer le PDF
        $filename = "equipement_{$equipment->getNumeroEquipement()}_{$agence}.pdf";
        $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
        
        // Retourner le PDF
        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\""
            ]
        );
    }
    
    /**
     * 
     */
    #[Route('/client/equipements/pdf/{agence}/{id}', name: 'client_equipements_pdf')]
    public function generateClientEquipementsPdf(Request $request, string $agence, string $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les filtres depuis les paramètres de la requête
        $clientAnneeFilter = $request->query->get('clientAnneeFilter', '');
        $clientVisiteFilter = $request->query->get('clientVisiteFilter', '');
        
        // Récupérer tous les équipements du client selon l'agence
        $equipments = $this->getEquipmentsByClientAndAgence($agence, $id, $entityManager);

        //Récupérer le client
        $clientSelectedInformations = null;
        switch ($agence) {
            case 'S10':
                $clientSelectedInformations = $entityManager->getRepository(ContactS10::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S40':
                $clientSelectedInformations = $entityManager->getRepository(ContactS40::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S50':
                $clientSelectedInformations = $entityManager->getRepository(ContactS50::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S60':
                $clientSelectedInformations = $entityManager->getRepository(ContactS60::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S70':
                $clientSelectedInformations = $entityManager->getRepository(ContactS70::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S80':
                $clientSelectedInformations = $entityManager->getRepository(ContactS80::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S100':
                $clientSelectedInformations = $entityManager->getRepository(ContactS100::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S120':
                $clientSelectedInformations = $entityManager->getRepository(ContactS120::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S130':    
                $clientSelectedInformations = $entityManager->getRepository(ContactS130::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S140':
                $clientSelectedInformations = $entityManager->getRepository(ContactS140::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S150':
                $clientSelectedInformations = $entityManager->getRepository(ContactS150::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S160':
                $clientSelectedInformations = $entityManager->getRepository(ContactS160::class)->findOneBy(['id_contact' => $id]);
                break;
            case 'S170':
                $clientSelectedInformations = $entityManager->getRepository(ContactS170::class)->findOneBy(['id_contact' => $id]);
                break;
            
            default:
                break;
        }
        if (empty($equipments)) {
            throw $this->createNotFoundException('Aucun équipement trouvé pour ce client');
        }
        
        // Appliquer les filtres si ils sont définis
        if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
            $equipments = array_filter($equipments, function($equipment) use ($clientAnneeFilter, $clientVisiteFilter) {
                $matches = true;
                
                // Filtre par année si défini
                if (!empty($clientAnneeFilter)) {
                    $annee_date_equipment = date("Y", strtotime($equipment->getDerniereVisite()));
                    $matches = $matches && ($annee_date_equipment == $clientAnneeFilter);
                }
                
                // Filtre par visite si défini
                if (!empty($clientVisiteFilter)) {
                    $matches = $matches && ($equipment->getVisite() == $clientVisiteFilter);
                }
                
                return $matches;
            });
        }
        
        // Vérifier s'il reste des équipements après filtrage
        if (empty($equipments)) {
            throw $this->createNotFoundException('Aucun équipement trouvé pour ce client avec les critères de filtrage sélectionnés');
        }
        
        // === CALCUL DES STATISTIQUES ===
        $etatsCount = [];
        $counterInexistant = 0;
        
        // Parcourir tous les équipements pour compter chaque état
        foreach ($equipments as $equipment) {
            $etat = $equipment->getEtat();
            
            if ($etat) {
                // Compter chaque état
                if (!isset($etatsCount[$etat])) {
                    $etatsCount[$etat] = 0;
                }
                $etatsCount[$etat]++;
                
                // Compter spécifiquement les équipements inexistants
                if ($etat === "Equipement non présent sur site") {
                    $counterInexistant++;
                }
            }
        }
        
        // Fonction pour déterminer le logo selon l'état
        $getLogoByEtat = function($etat) {
            switch ($etat) {
                case "Rien à signaler le jour de la visite. Fonctionnement ok":
                    return 'vert';
                case "Travaux à prévoir":
                    return 'orange';
                case "Travaux curatifs":
                case "Equipement à l'arrêt le jour de la visite":
                case "Equipement mis à l'arrêt lors de l'intervention":
                    return 'rouge';
                case "Equipement inaccessible le jour de la visite":
                case "Equipement non présent sur site":
                    return 'noir';
                default:
                    return 'noir';
            }
        };
        
        // Créer le tableau de statistiques
        $statistiques = [
            'etatsCount' => $etatsCount,
            'counterInexistant' => $counterInexistant,
            'getLogoByEtat' => $getLogoByEtat
        ];
        
        $equipmentsWithPictures = [];
        
        // Récupérer la date de dernière visite
        $dateDeDerniererVisite = "";

        // Pour chaque équipement filtré, récupérer ses photos
        foreach ($equipments as $equipment) {
            $picturesArray = $entityManager->getRepository(Form::class)->findBy([
                'code_equipement' => $equipment->getNumeroEquipement(), 
                'raison_sociale_visite' => $equipment->getRaisonSociale() . "\\" . $equipment->getVisite()
            ]);
            
            $picturesData = $entityManager->getRepository(Form::class)->getPictureArrayByIdEquipment($picturesArray, $entityManager, $equipment);
            
            $equipmentsWithPictures[] = [
                'equipment' => $equipment,
                'pictures' => $picturesData
            ];
        } 

        $equipementsSupplementaires = array_filter($equipmentsWithPictures, function($equipement) {
            return $equipement['equipment']->isEnMaintenance() === false;
        });
        $equipementsNonPresents = [];
        foreach ($equipmentsWithPictures as $equipement) {
            if ($equipement['equipment']->getEtat() === "Equipement non présent sur site" || $equipement['equipment']->getEtat() === "G") {
                $equipementsNonPresents[] = $equipement;
            }
            $dateDeDerniererVisite = $equipement['equipment']->getDerniereVisite();
        }

        // Déterminer l'URL de l'image en fonction du nom de l'agence
        $imageUrl = $this->getImageUrlForAgency($agence);
        // dd($imageUrl);
        // Générer le HTML pour le PDF
        $html = $this->renderView('pdf/equipements.html.twig', [
            'equipmentsWithPictures' => $equipmentsWithPictures,
            'equipementsSupplementaires' => $equipementsSupplementaires,
            'equipementsNonPresents' => $equipementsNonPresents,
            'clientId' => $id,
            'agence' => $agence,
            'imageUrl' => $imageUrl,
            'clientAnneeFilter' => $clientAnneeFilter,
            'clientVisiteFilter' => $clientVisiteFilter,
            'statistiques' => $statistiques, // 🎯 Nouvelle variable ajoutée,
            'dateDeDerniererVisite' => $dateDeDerniererVisite,
            'clientSelectedInformations' => $clientSelectedInformations,
            'isFiltered' => !empty($clientAnneeFilter) || !empty($clientVisiteFilter)
        ]);
        
        // Générer le nom de fichier avec les filtres si applicables
        $filename = "equipements_client_{$id}_{$agence}";
        if (!empty($clientAnneeFilter) || !empty($clientVisiteFilter)) {
            $filename .= '_filtered';
            if (!empty($clientAnneeFilter)) {
                $filename .= '_' . $clientAnneeFilter;
            }
            if (!empty($clientVisiteFilter)) {
                $filename .= '_' . str_replace(' ', '_', $clientVisiteFilter);
            }
        }
        $filename .= '.pdf';
        
        // Générer le PDF
        $pdfContent = $this->pdfGenerator->generatePdf($html, $filename);
        
        // Retourner le PDF
        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$filename\""
            ]
        );
    }
    
    private function getEquipmentByAgence(string $agence, string $id, EntityManagerInterface $entityManager)
    {
        switch ($agence) {
            case 'S10':
                return $entityManager->getRepository(EquipementS10::class)->findOneBy(['id' => $id]);
            case 'S40':
                return $entityManager->getRepository(EquipementS40::class)->findOneBy(['id' => $id]);
            case 'S50':
                return $entityManager->getRepository(EquipementS50::class)->findOneBy(['id' => $id]);
            case 'S60':
                return $entityManager->getRepository(EquipementS60::class)->findOneBy(['id' => $id]);
            case 'S70':
                return $entityManager->getRepository(EquipementS70::class)->findOneBy(['id' => $id]);
            case 'S80':
                return $entityManager->getRepository(EquipementS80::class)->findOneBy(['id' => $id]);
            case 'S100':
                return $entityManager->getRepository(EquipementS100::class)->findOneBy(['id' => $id]);
            case 'S120':
                return $entityManager->getRepository(EquipementS120::class)->findOneBy(['id' => $id]);
            case 'S130':
                return $entityManager->getRepository(EquipementS130::class)->findOneBy(['id' => $id]);
            case 'S140':
                return $entityManager->getRepository(EquipementS140::class)->findOneBy(['id' => $id]);
            case 'S150':
                return $entityManager->getRepository(EquipementS150::class)->findOneBy(['id' => $id]);
            case 'S160':
                return $entityManager->getRepository(EquipementS160::class)->findOneBy(['id' => $id]);
            case 'S170':
                return $entityManager->getRepository(EquipementS170::class)->findOneBy(['id' => $id]);
            default:
                return null;
        }
    }
    
    private function getEquipmentsByClientAndAgence(string $agence, string $id, EntityManagerInterface $entityManager)
    {
        switch ($agence) {
            case 'S10':
                return $entityManager->getRepository(EquipementS10::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S40':
                return $entityManager->getRepository(EquipementS40::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S50':
                return $entityManager->getRepository(EquipementS50::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S60':
                return $entityManager->getRepository(EquipementS60::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S70':
                return $entityManager->getRepository(EquipementS70::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S80':
                return $entityManager->getRepository(EquipementS80::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S100':
                return $entityManager->getRepository(EquipementS100::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S120':
                return $entityManager->getRepository(EquipementS120::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S130':
                return $entityManager->getRepository(EquipementS130::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S140':
                return $entityManager->getRepository(EquipementS140::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S150':
                return $entityManager->getRepository(EquipementS150::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S160':
                return $entityManager->getRepository(EquipementS160::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            case 'S170':
                return $entityManager->getRepository(EquipementS170::class)->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
            default:
                return [];
        }
    }

    private function getImageUrlForAgency(string $agencyName): string
    {
        // Assurer que cela renvoie un chemin absolu
        $basePath = 'https://www.pdf.somafi-group.fr/background/';

        // Assurez-vous d'ajouter vos conditions pour les URL spécifiques
        switch ($agencyName) {
            case 'S10':
                return $basePath . 'group.jpg';
            case 'S40':
                return $basePath . 'st-etienne.jpg';
            case 'S50':
                return $basePath . 'grenoble.jpg';
            case 'S60':
                return $basePath . 'lyon.jpg';
            case 'S70':
                return $basePath . 'bordeaux.jpg';
            case 'S80':
                return $basePath . 'paris.jpg';
            case 'S100':
                return $basePath . 'montpellier.jpg';
            case 'S120':
                return $basePath . 'portland.jpg';
            case 'S130':
                return $basePath . 'toulouse.jpg';
            case 'S140':
                return $basePath . 'grand-est.jpg';
            case 'S150':
                return $basePath . 'paca.jpg';
            case 'S160':
                return $basePath . 'rouen.jpg';
            case 'S170':
                return $basePath . 'rennes.jpg';
            default:
                return $basePath . 'default.jpg'; // Image par défaut
        }
    }
}