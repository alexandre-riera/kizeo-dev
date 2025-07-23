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
        
        // Configuration de base
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

        // 🎯 CONFIGURATION MARGES RENFORCÉE
        $options->set('marginTop', 0);
        $options->set('marginBottom', 0);
        $options->set('marginLeft', 0);
        $options->set('marginRight', 0);
        
        // 🔧 Options supplémentaires pour éliminer les marges
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        
        // Pour les images base64
        $options->set('enable_font_subsetting', true);
        $options->set('defaultMediaType', 'print');
        
        // 🎯 NOUVELLE OPTION : Ajuster le CSS pour supprimer les marges
        // $htmlWithExtraCSS = $this->addAntiMarginCSS($html);
        
        $dompdf = new Dompdf($options);
        // $dompdf->loadHtml($htmlWithExtraCSS);
        
        // 🔧 Configuration du papier avec marges explicites à 0
        $dompdf->setPaper('A4', 'portrait');
        
        // 🎯 NOUVEAU : Forcer les marges à 0 après le setPaper
        // $dompdf->getCanvas()->get_cpdf()->selectFont('Helvetica');
        
        $dompdf->render();
        
        return $dompdf->output();
    }

    /**
     * 🆕 Méthode pour ajouter du CSS anti-marge directement dans le HTML
     */
    private function addAntiMarginCSS($html)
    {
        $antiMarginCSS = "
        <style>
            @page { 
                margin: 0mm 0mm 0mm 0mm !important; 
                padding: 0mm 0mm 0mm 0mm !important;
                size: A4 portrait;
            }
            html, body { 
                margin: 0 !important; 
                padding: 0 !important; 
                width: 100% !important;
                height: 100% !important;
            }
            * { 
                box-sizing: border-box; 
            }
        </style>";
        
        // Injecter le CSS juste après la balise <head> ou au début du body
        if (strpos($html, '</head>') !== false) {
            $html = str_replace('</head>', $antiMarginCSS . '</head>', $html);
        } else {
            // Si pas de balise head, l'ajouter au début du body
            $html = str_replace('<body>', '<body>' . $antiMarginCSS, $html);
        }
        
        return $html;
    }
    
    /**
     * 🆕 Méthode alternative pour PDF sans aucune marge
     */
    public function generatePdfNoMargins($html, $filename = 'document.pdf')
    {
        $options = new Options();
        
        // Configuration ultra-stricte sans marges
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('marginTop', 0);
        $options->set('marginBottom', 0);
        $options->set('marginLeft', 0);
        $options->set('marginRight', 0);
        
        // HTML modifié pour supprimer complètement les marges
        $noMarginHtml = "
        <!DOCTYPE html>
        <html style='margin:0;padding:0;'>
        <head>
            <meta charset='UTF-8'>
            <style>
                @page { margin: 0; size: A4 portrait; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body { margin: 0 !important; padding: 0 !important; width: 100%; }
            </style>
        </head>
        <body style='margin:0;padding:0;'>
            {$html}
        </body>
        </html>";
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($noMarginHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }

    public function savePdf($html, $filename = 'document.pdf')
    {
        $pdfContent = $this->generatePdf($html, $filename);
        file_put_contents($filename, $pdfContent);
    }
    
    /**
     * 🆕 Sauvegarder PDF sans marges
     */
    public function savePdfNoMargins($html, $filename = 'document.pdf')
    {
        $pdfContent = $this->generatePdfNoMargins($html, $filename);
        file_put_contents($filename, $pdfContent);
    }
}
