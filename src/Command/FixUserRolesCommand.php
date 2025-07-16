<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-user-roles',
    description: 'Corrige les rôles des utilisateurs stockés comme objets en base de données',
)]
class FixUserRolesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Correction des rôles utilisateurs');

        // Récupérer tous les utilisateurs
        $users = $this->entityManager->getRepository(User::class)->findAll();
        
        $fixedCount = 0;
        $totalUsers = count($users);

        $io->progressStart($totalUsers);

        foreach ($users as $user) {
            $roles = $user->getRoles();
            $originalRoles = $this->getRawRolesFromDatabase($user->getId());
            
            // Vérifier si les rôles sont stockés comme un objet
            if (is_string($originalRoles)) {
                $decodedRoles = json_decode($originalRoles, true);
                
                // Si c'est un objet associatif comme {"1":"ROLE_S50"}
                if (is_array($decodedRoles) && !empty($decodedRoles) && !$this->isIndexedArray($decodedRoles)) {
                    $fixedRoles = array_values($decodedRoles);
                    
                    // Supprimer ROLE_USER pour éviter les doublons
                    $fixedRoles = array_filter($fixedRoles, function($role) {
                        return $role !== 'ROLE_USER';
                    });
                    
                    $user->setRoles($fixedRoles);
                    $this->entityManager->persist($user);
                    
                    $fixedCount++;
                    
                    $io->writeln(sprintf(
                        'Utilisateur %s: %s → %s',
                        $user->getEmail(),
                        $originalRoles,
                        json_encode($fixedRoles)
                    ));
                }
            }
            
            $io->progressAdvance();
        }

        $io->progressFinish();

        if ($fixedCount > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('%d utilisateurs ont été corrigés sur %d au total.', $fixedCount, $totalUsers));
        } else {
            $io->note('Aucune correction nécessaire. Tous les rôles sont déjà au bon format.');
        }

        // Afficher un résumé des rôles par agence
        $this->displayRolesSummary($io);

        return Command::SUCCESS;
    }

    private function getRawRolesFromDatabase(int $userId): mixed
    {
        $query = $this->entityManager->createQuery(
            'SELECT u.roles FROM App\Entity\User u WHERE u.id = :id'
        );
        $query->setParameter('id', $userId);
        
        $result = $query->getOneOrNullResult();
        return $result ? $result['roles'] : null;
    }

    private function isIndexedArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    private function displayRolesSummary(SymfonyStyle $io): void
    {
        $io->section('Résumé des rôles par agence');

        $users = $this->entityManager->getRepository(User::class)->findAll();
        $agencyRoles = [];

        foreach ($users as $user) {
            $roles = $user->getRoles();
            foreach ($roles as $role) {
                if (preg_match('/^ROLE_(S\d+)$/', $role, $matches)) {
                    $agencyCode = $matches[1];
                    if (!isset($agencyRoles[$agencyCode])) {
                        $agencyRoles[$agencyCode] = [];
                    }
                    $agencyRoles[$agencyCode][] = $user->getEmail();
                }
            }
        }

        if (empty($agencyRoles)) {
            $io->note('Aucun rôle d\'agence trouvé.');
            return;
        }

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

        $tableRows = [];
        foreach ($agencyRoles as $code => $users) {
            $agencyName = $agencyNames[$code] ?? $code;
            $tableRows[] = [
                $code,
                $agencyName,
                count($users),
                implode(', ', $users)
            ];
        }

        $io->table(
            ['Code', 'Agence', 'Nb Users', 'Utilisateurs'],
            $tableRows
        );
    }
}