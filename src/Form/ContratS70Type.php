<?php

namespace App\Form;

use App\Entity\ContratS70;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContratS70Type extends AbstractType
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContratS70::class,
        ]);
    }
}
