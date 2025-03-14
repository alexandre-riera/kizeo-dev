<?php

namespace App\Form;

use App\Entity\ContratS50;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class ContratS50Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero_contrat')
            ->add('date_signature', null, [
                'widget' => 'single_text'
            ])
            ->add('duree')
            ->add('tacite_reconduction')
            ->add('valorisation')
            ->add('nombre_equipement')
            ->add('nombre_visite')
            ->add('date_resiliation', null, [
                'widget' => 'single_text'
            ])
            ->add('statut')
            ->add('date_previsionnelle_1')
            ->add('date_previsionnelle_2')
            ->add('date_effective_1')
            ->add('date_effective_2')
            ->add('id_contact')
            ->add('equipements', CollectionType::class, [
                'entry_type' => EquipementS50Type::class,
                'allow_add' => true,
                'by_reference' => false,
                'allow_delete' => true,
                'prototype' => true,
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContratS50::class,
        ]);
    }
}
