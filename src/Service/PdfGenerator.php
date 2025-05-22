<?php
// src/Service/PdfGenerator.php
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator
{
    public function generatePdf($html, $filename = 'document.pdf')
    {
        // Initialisation de Dompdf (version simple sans Options)
        $dompdf = new Dompdf();
        
        // Configuration des options directement sur l'instance
        $dompdf->getOptions()->setIsHtml5ParserEnabled(true);
        $dompdf->getOptions()->setIsRemoteEnabled(true);
        
        // Charger le HTML
        $dompdf->loadHtml($html);
        
        // Configuration du format et orientation
        $dompdf->setPaper('A4', 'portrait');
        
        // Rendu du PDF
        $dompdf->render();
        
        return $dompdf->output();
    }

}
