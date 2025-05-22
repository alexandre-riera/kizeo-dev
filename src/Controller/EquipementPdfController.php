<?php
// src/Controller/EquipementPdfController.php
namespace App\Controller;

use App\Repository\EquipementS10Repository;
use App\Repository\FormRepository;
use App\Service\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EquipementPdfController extends AbstractController
{
    private $equipementRepository;
    private $formRepository;
    private $pdfGenerator;
    
    public function __construct(
        EquipementS10Repository $equipementRepository,
        FormRepository $formRepository,
        PdfGenerator $pdfGenerator
    ) {
        $this->equipementRepository = $equipementRepository;
        $this->formRepository = $formRepository;
        $this->pdfGenerator = $pdfGenerator;
    }
    
    /**
     * 
     */
    #[Route('/client/{id}/equipements/pdf', name: 'client_equipements_pdf', methods: ['GET'])]
    public function generateClientEquipementsPdf($id): Response
    {
        // Récupérer les équipements du client
        $equipements = $this->equipementRepository->findBy(['id_contact' => $id], ['numero_equipement' => 'ASC']);
        
        // Récupérer les photos associées aux équipements
        $photos = $this->formRepository->findBy(['id_client' => $id]);
        
        // Générer le HTML pour le PDF
        $html = $this->renderView('pdf/equipements.html.twig', [
            'equipements' => $equipements,
            'photos' => $photos,
            'client_id' => $id
        ]);
        
        // Générer le PDF
        $pdfContent = $this->pdfGenerator->generatePdf($html, "equipements_client_$id.pdf");
        
        // Retourner le PDF en tant que réponse
        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=equipements_client_$id.pdf"
            ]
        );
    }
}