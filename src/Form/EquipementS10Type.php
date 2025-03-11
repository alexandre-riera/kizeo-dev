<?php

namespace App\Form;

use App\Entity\ContratS10;
use App\Entity\EquipementS10;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipementS10Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero_equipement')
            ->add('libelle_equipement')
            ->add('mode_fonctionnement')
            ->add('repere_site_client')
            ->add('mise_en_service')
            ->add('numero_de_serie')
            ->add('marque')
            ->add('hauteur')
            ->add('largeur')
            ->add('longueur')
            ->add('plaque_signaletique')
            ->add('anomalies')
            ->add('etat')
            ->add('derniere_visite')
            ->add('trigramme_tech')
            ->add('id_contact')
            ->add('code_societe')
            ->add('raison_sociale')
            ->add('signature_tech')
            ->add('ifExistDB')
            ->add('code_agence')
            ->add('hauteur_nacelle')
            ->add('modele_nacelle')
            ->add('test')
            ->add('statut_de_maintenance')
            ->add('date_enregistrement')
            ->add('presenceCarnetEntretien')
            ->add('statutConformite')
            ->add('dateMiseEnConformite')
            ->add('isEtatDesLieuxFait')
            ->add('isEnMaintenance')
            ->add('visite')
            ->add('contratS10', EntityType::class, [
                'class' => ContratS10::class,
'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EquipementS10::class,
        ]);
    }
}
