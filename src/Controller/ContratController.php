<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ContratController extends AbstractController
{
    #[Route('/api/contrats', name: 'app_contrat', methods: ['GET'])]
    public function getContrats(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Welcome to the Contrat controller!',
            'path' => 'src/Controller/ContratController.php',
        ]);
    }
}
