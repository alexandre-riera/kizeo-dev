<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('first_name', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Le prénom doit comporter au moins {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('last_name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Le nom doit comporter au moins {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email(['message' => 'L\'email n\'est pas valide'])
                ]
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices'  => [
                    'Super admin' => 'ROLE_ADMIN',
                    'SOMAFI testeur toutes agences' => 'ROLE_SOMAFI',
                    'Utilisateur SOMAFI éditeur' => 'ROLE_SOMAFI_EDIT',
                    'Group' => 'ROLE_S10',
                    'St Etienne' => 'ROLE_S40',
                    'Grenoble' => 'ROLE_S50',
                    'Lyon' => 'ROLE_S60',
                    'Bordeaux' => 'ROLE_S70',
                    'Paris Nord' => 'ROLE_S80',
                    'Montpellier' => 'ROLE_S100',
                    'Hauts de France' => 'ROLE_S120',
                    'Toulouse' => 'ROLE_S130',
                    'Epinal' => 'ROLE_S140',
                    'PACA' => 'ROLE_S150',
                    'Rouen' => 'ROLE_S160',
                    'Rennes' => 'ROLE_S170',
                    'Admin KUEHNE' => 'ROLE_ADMIN_KUEHNE',
                    'Utilisateur KUEHNE' => 'ROLE_USER_KUEHNE',
                    'Admin GLS' => 'ROLE_ADMIN_GLS',
                    'Utilisateur GLS' => 'ROLE_USER_GLS',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
                'constraints' => [
                    new NotBlank(['message' => 'Au moins un rôle doit être sélectionné'])
                ]
            ]);

        // Gestion du mot de passe
        $passwordConstraints = [
            new Length([
                'min' => 6,
                'minMessage' => 'Le mot de passe doit comporter au moins {{ limit }} caractères',
                'max' => 4096,
            ]),
        ];
        
        if (!$options['is_edit']) {
            $passwordConstraints[] = new NotBlank(['message' => 'Le mot de passe est obligatoire']);
        }
        
        $builder->add('password', PasswordType::class, [
            'label' => 'Mot de passe',
            'mapped' => false,
            'required' => !$options['is_edit'],
            'attr' => ['class' => 'form-control'],
            'constraints' => $passwordConstraints,
            'help' => $options['is_edit'] ? 'Laissez vide pour conserver le mot de passe actuel' : null,
        ]);

        // Event listeners pour traiter les rôles
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $user = $event->getData();
            if ($user && $user->getRoles()) {
                $roles = $user->getRoles();
                // Retirer ROLE_USER automatique et réindexer
                if (is_array($roles)) {
                    $roles = array_values(array_filter($roles, function($role) {
                        return $role !== 'ROLE_USER';
                    }));
                    $user->setRoles($roles);
                }
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            
            // S'assurer que les champs texte ne sont pas vides
            if (isset($data['first_name'])) {
                $data['first_name'] = trim($data['first_name']);
            }
            if (isset($data['last_name'])) {
                $data['last_name'] = trim($data['last_name']);
            }
            if (isset($data['email'])) {
                $data['email'] = trim($data['email']);
            }
            
            // Traitement des rôles
            if (isset($data['roles']) && is_array($data['roles'])) {
                // Filtrer les rôles vides et s'assurer que c'est un tableau indexé numériquement
                $data['roles'] = array_values(array_filter($data['roles']));
                
                // S'assurer qu'au moins un rôle est sélectionné
                if (empty($data['roles'])) {
                    $data['roles'] = ['ROLE_USER']; // Valeur par défaut
                }
            }
            
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            // Configuration CSRF simplifiée - utilise le nom du formulaire par défaut
            'csrf_protection' => true,
        ]);
    }
}