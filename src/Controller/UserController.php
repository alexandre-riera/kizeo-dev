<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

#[Route('/admin/user')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAllUserAlphabetically(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        
        // Créer le formulaire avec gestion CSRF explicite
        $form = $this->createForm(UserFormType::class, $user, [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'user_item',
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Debug CSRF (à retirer après résolution)
            if (!$this->isCsrfTokenValid('user_item', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide. Veuillez réessayer.');
                return $this->render('user/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            if ($form->isValid()) {
                try {
                    // Récupérer le mot de passe du formulaire
                    $plainPassword = $form->get('password')->getData();
                    
                    if (empty($plainPassword)) {
                        $this->addFlash('error', 'Le mot de passe est obligatoire.');
                        return $this->render('user/new.html.twig', [
                            'user' => $user,
                            'form' => $form,
                        ]);
                    }

                    // Hacher le mot de passe
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                    
                    // Vérifier que l'utilisateur a au moins un rôle (autre que ROLE_USER)
                    $roles = $user->getRoles();
                    $nonUserRoles = array_filter($roles, fn($role) => $role !== 'ROLE_USER');
                    
                    if (empty($nonUserRoles)) {
                        $this->addFlash('error', 'Au moins un rôle doit être assigné à l\'utilisateur.');
                        return $this->render('user/new.html.twig', [
                            'user' => $user,
                            'form' => $form,
                        ]);
                    }
                    
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'L\'utilisateur a été créé avec succès.');
                    return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
                    
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur.');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de la création de l\'utilisateur : ' . $e->getMessage());
                }
            } else {
                // Afficher les erreurs de validation
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('error', 'Erreurs de validation : ' . implode(', ', $errors));
                }
            }
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserFormType::class, $user, [
            'is_edit' => true,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'user_edit_' . $user->getId(),
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérifier si un nouveau mot de passe a été fourni
                $password = $form->get('password')->getData();
                if ($password) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);
                }
                
                $entityManager->flush();

                $this->addFlash('success', 'L\'utilisateur a été modifié avec succès.');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la modification de l\'utilisateur : ' . $e->getMessage());
            }
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($user);
                $entityManager->flush();
                $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide pour la suppression.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}