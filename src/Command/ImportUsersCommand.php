<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-users',
    description: 'Import users from JSON file',
)]
class ImportUsersCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Chemin vers votre fichier JSON
        $jsonFile = __DIR__ . '/../../public/uploads/users.json';
        
        if (!file_exists($jsonFile)) {
            $io->error('Le fichier JSON n\'existe pas!');
            return Command::FAILURE;
        }
        
        $jsonContent = file_get_contents($jsonFile);
        $users = json_decode($jsonContent, true);
        
        if (!$users) {
            $io->error('Impossible de décoder le fichier JSON!');
            return Command::FAILURE;
        }
        
        $count = 0;
        
        foreach ($users as $userData) {
            // Vérifier si l'email existe et qu'il est valide
            if (empty($userData['EMAIL']) || !filter_var($userData['EMAIL'], FILTER_VALIDATE_EMAIL)) {
                $io->warning('Email invalide: ' . ($userData['EMAIL'] ?? 'N/A') . ' pour ' . $userData['NOM'] . ' ' . $userData['PRENOM']);
                continue;
            }
            
            // Vérifier si l'utilisateur existe déjà
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userData['EMAIL']]);
            
            if ($existingUser) {
                $io->note('L\'utilisateur avec l\'email ' . $userData['EMAIL'] . ' existe déjà.');
                continue;
            }
            
            $user = new User();
            $user->setLastName($userData['NOM']);
            $user->setFirstName($userData['PRENOM']);
            $user->setEmail($userData['EMAIL']);
            $user->setRoles(['ROLE_SOMAFI']);
            
            // Hacher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $userData['MOT DE PASSE'] ?? 'password123' // Mot de passe par défaut si vide
            );
            $user->setPassword($hashedPassword);
            
            $this->entityManager->persist($user);
            $count++;
            
            // Flush tous les 20 utilisateurs pour éviter une surcharge mémoire
            if ($count % 20 === 0) {
                $this->entityManager->flush();
                $io->info('Importé ' . $count . ' utilisateurs...');
            }
        }
        
        // Flush final
        $this->entityManager->flush();
        
        $io->success($count . ' utilisateurs ont été importés avec succès!');
        
        return Command::SUCCESS;
    }
}
