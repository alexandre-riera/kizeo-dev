<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SocieteController extends AbstractController
{
    #[Route('/api/societes', name: 'app_societe', methods: ['GET'])]
    public function getSocietes(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Welcome to the societe controller!',
            'path' => 'src/Controller/SocieteController.php',
        ]);
    }
}
