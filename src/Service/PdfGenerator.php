<?php
// src/Service/PdfGenerator.php
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator
{
    public function generatePdf($html, $filename = 'document.pdf')
    {
        // Configuration des options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Pour permettre le chargement d'images externes
        
        // Initialisation de Dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        
        // Configuration du format et orientation
        $dompdf->setPaper('A4', 'portrait');
        
        // Rendu du PDF
        $dompdf->render();
        
        return $dompdf->output();
    }
}
