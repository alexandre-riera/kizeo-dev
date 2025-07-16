<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AgencyExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getAgencyName', [$this, 'getAgencyName']),
        ];
    }

    public function getAgencyName(string $code): string
    {
        $agencyNames = [
            'S10' => 'Group',
            'S40' => 'St Etienne',
            'S50' => 'Grenoble',
            'S60' => 'Lyon',
            'S70' => 'Bordeaux',
            'S80' => 'ParisNord',
            'S100' => 'Montpellier',
            'S120' => 'HautsDeFrance',
            'S130' => 'Toulouse',
            'S140' => 'SMP',
            'S150' => 'PACA',
            'S160' => 'Rouen',
            'S170' => 'Rennes',
        ];
        
        return $agencyNames[$code] ?? $code;
    }
}