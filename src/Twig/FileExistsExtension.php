<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FileExistsExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('fileExists', [$this, 'fileExists']),
        ];
    }

    public function fileExists($path)
    {
        return file_exists($path);
    }
}