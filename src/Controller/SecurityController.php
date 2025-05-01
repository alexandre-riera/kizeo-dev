<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordRequestType;
use App\Form\ResetPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout()
    {
        // throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/forgot-password', name: 'app_forgot_password_request')]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        TokenGeneratorInterface $tokenGenerator
    ): Response {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            
            $user = $entityManager->getRepository(User::class)->findOneBy([
                'email' => $email,
            ]);

            // Ne pas révéler si l'utilisateur existe ou non
            if ($user) {
                $token = $tokenGenerator->generateToken();
                $user->setResetToken($token);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $entityManager->flush();

                $url = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                
                $email = (new TemplatedEmail())
                    ->from(new Address('contact@backend-kizeo.somafi-group.fr', 'Gestion de parc'))
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->htmlTemplate('security/reset_password_email.html.twig')
                    ->context([
                        'resetUrl' => $url,
                        'user' => $user,
                        'expiration_date' => new \DateTime('+1 hour')
                    ]);
                
                $mailer->send($email);
            }

            $this->addFlash('success', 'Un email vous a été envoyé pour réinitialiser votre mot de passe.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }
    
    #[Route(path: '/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        Request $request,
        string $token,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'resetToken' => $token,
        ]);

        // Vérifier si le token est valide
        if (!$user || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Réinitialiser le token
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            
            // Mettre à jour le mot de passe
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );
            $user->setPassword($encodedPassword);
            
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre mot de passe a été réinitialisé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}
