<?php
// src/Service/PdfGenerator.php
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator
{
    public function generatePdf($html, $filename = 'document.pdf')
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPHPEnabled', true);
        $options->set('debugKeepTemp', false);
        $options->set('debugCss', false);
        $options->set('debugLayout', false);
        $options->set('debugLayoutLines', false);
        $options->set('debugLayoutBlocks', false);
        $options->set('debugLayoutInline', false);
        $options->set('debugLayoutPaddingBox', false);
        
        // Pour les images base64
        $options->set('enable_font_subsetting', true);
        $options->set('defaultMediaType', 'print');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }

    public function savePdf($html, $filename = 'document.pdf')
    {
        $pdfContent = $this->generatePdf($html, $filename);
        file_put_contents($filename, $pdfContent);
    }
}
