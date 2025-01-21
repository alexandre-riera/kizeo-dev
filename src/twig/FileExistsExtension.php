<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FileExistsExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('file_exists', [$this, 'file_exists']),
        ];
    }

    public function fileExists($path)
    {
        return file_exists($path);
    }
}