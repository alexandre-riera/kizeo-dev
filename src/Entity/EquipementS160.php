<?php

namespace App\Entity;

use App\Repository\EquipementS160Repository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipementS160Repository::class)]
class EquipementS160
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numero_equipement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle_equipement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mode_fonctionnement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repere_site_client = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mise_en_service = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numero_de_serie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hauteur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $largeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longueur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $plaque_signaletique = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $anomalies = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $derniere_visite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trigramme_tech = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $id_contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code_societe = null;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $raison_sociale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signature_tech = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ifExistDB = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code_agence = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hauteur_nacelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modele_nacelle = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $test = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut_de_maintenance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $date_enregistrement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $presenceCarnetEntretien = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statutConformite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dateMiseEnConformite = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isEtatDesLieuxFait = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isEnMaintenance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $visite = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?ContratS160 $contratS160 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remplace_par = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numero_identification = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroEquipement(): ?string
    {
        return $this->numero_equipement;
    }

    public function setNumeroEquipement(string $numero_equipement): static
    {
        $this->numero_equipement = $numero_equipement;

        return $this;
    }

    public function getLibelleEquipement(): ?string
    {
        return $this->libelle_equipement;
    }

    public function setLibelleEquipement(string $libelle_equipement): static
    {
        $this->libelle_equipement = $libelle_equipement;

        return $this;
    }

    public function getModeFonctionnement(): ?string
    {
        return $this->mode_fonctionnement;
    }

    public function setModeFonctionnement(string $mode_fonctionnement): static
    {
        $this->mode_fonctionnement = $mode_fonctionnement;

        return $this;
    }

    public function getRepereSiteClient(): ?string
    {
        return $this->repere_site_client;
    }

    public function setRepereSiteClient(string $repere_site_client): static
    {
        $this->repere_site_client = $repere_site_client;

        return $this;
    }

    public function getMiseEnService(): ?string
    {
        return $this->mise_en_service;
    }

    public function setMiseEnService(string $mise_en_service): static
    {
        $this->mise_en_service = $mise_en_service;

        return $this;
    }

    public function getNumeroDeSerie(): ?string
    {
        return $this->numero_de_serie;
    }

    public function setNumeroDeSerie(string $numero_de_serie): static
    {
        $this->numero_de_serie = $numero_de_serie;

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getHauteur(): ?string
    {
        return $this->hauteur;
    }

    public function setHauteur(string $hauteur): static
    {
        $this->hauteur = $hauteur;

        return $this;
    }

    public function getLargeur(): ?string
    {
        return $this->largeur;
    }

    public function setLargeur(string $largeur): static
    {
        $this->largeur = $largeur;

        return $this;
    }

    public function getPlaqueSignaletique(): ?string
    {
        return $this->plaque_signaletique;
    }

    public function setPlaqueSignaletique(string $plaque_signaletique): static
    {
        $this->plaque_signaletique = $plaque_signaletique;

        return $this;
    }

    public function getAnomalies(): ?string
    {
        return $this->anomalies;
    }

    public function setAnomalies(?string $anomalies): static
    {
        $this->anomalies = $anomalies;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getDerniereVisite(): ?string
    {
        return $this->derniere_visite;
    }

    public function setDerniereVisite(?string $derniere_visite): static
    {
        $this->derniere_visite = $derniere_visite;

        return $this;
    }

    public function getTrigrammeTech(): ?string
    {
        return $this->trigramme_tech;
    }

    public function setTrigrammeTech(string $trigramme_tech): static
    {
        $this->trigramme_tech = $trigramme_tech;

        return $this;
    }

    public function getIdContact(): ?string
    {
        return $this->id_contact;
    }

    public function setIdContact(?string $id_contact): static
    {
        $this->id_contact = $id_contact;

        return $this;
    }

    public function getCodeSociete(): ?string
    {
        return $this->code_societe;
    }

    public function setCodeSociete(?string $code_societe): static
    {
        $this->code_societe = $code_societe;

        return $this;
    }

    public function getSignatureTech(): ?string
    {
        return $this->signature_tech;
    }

    public function setSignatureTech(?string $signature_tech): static
    {
        $this->signature_tech = $signature_tech;

        return $this;
    }

    public function getIfExistDB(): ?string
    {
        return $this->ifExistDB;
    }

    public function setIfExistDB(?string $ifExistDB): static
    {
        $this->ifExistDB = $ifExistDB;

        return $this;
    }

    public function getCodeAgence(): ?string
    {
        return $this->code_agence;
    }

    public function setCodeAgence(?string $code_agence): static
    {
        $this->code_agence = $code_agence;

        return $this;
    }

    public function getHauteurNacelle(): ?string
    {
        return $this->hauteur_nacelle;
    }

    public function setHauteurNacelle(?string $hauteur_nacelle): static
    {
        $this->hauteur_nacelle = $hauteur_nacelle;

        return $this;
    }

    public function getModeleNacelle(): ?string
    {
        return $this->modele_nacelle;
    }

    public function setModeleNacelle(?string $modele_nacelle): static
    {
        $this->modele_nacelle = $modele_nacelle;

        return $this;
    }

    public function getRaisonSociale(): ?string
    {
        return $this->raison_sociale;
    }

    public function setRaisonSociale(?string $raison_sociale): static
    {
        $this->raison_sociale = $raison_sociale;

        return $this;
    }

    public function getTest(): ?string
    {
        return $this->test;
    }

    public function setTest(string $test): static
    {
        $this->test = $test;

        return $this;
    }

    public function getStatutDeMaintenance(): ?string
    {
        return $this->statut_de_maintenance;
    }

    public function setStatutDeMaintenance(?string $statut_de_maintenance): static
    {
        $this->statut_de_maintenance = $statut_de_maintenance;

        return $this;
    }

    public function getDateEnregistrement(): ?string
    {
        return $this->date_enregistrement;
    }

    public function setDateEnregistrement(?string $date_enregistrement): static
    {
        $this->date_enregistrement = $date_enregistrement;

        return $this;
    }

    public function getPresenceCarnetEntretien(): ?string
    {
        return $this->presenceCarnetEntretien;
    }

    public function setPresenceCarnetEntretien(?string $presenceCarnetEntretien): static
    {
        $this->presenceCarnetEntretien = $presenceCarnetEntretien;

        return $this;
    }

    public function getStatutConformite(): ?string
    {
        return $this->statutConformite;
    }

    public function setStatutConformite(?string $statutConformite): static
    {
        $this->statutConformite = $statutConformite;

        return $this;
    }

    public function getDateMiseEnConformite(): ?string
    {
        return $this->dateMiseEnConformite;
    }

    public function setDateMiseEnConformite(?string $dateMiseEnConformite): static
    {
        $this->dateMiseEnConformite = $dateMiseEnConformite;

        return $this;
    }

    public function getLongueur(): ?string
    {
        return $this->longueur;
    }

    public function setLongueur(?string $longueur): static
    {
        $this->longueur = $longueur;

        return $this;
    }

    public function isEtatDesLieuxFait(): ?bool
    {
        return $this->isEtatDesLieuxFait;
    }

    public function setEtatDesLieuxFait(?bool $isEtatDesLieuxFait): static
    {
        $this->isEtatDesLieuxFait = $isEtatDesLieuxFait;

        return $this;
    }

    public function isEnMaintenance(): ?bool
    {
        return $this->isEnMaintenance;
    }

    public function setEnMaintenance(?bool $isEnMaintenance): static
    {
        $this->isEnMaintenance = $isEnMaintenance;

        return $this;
    }

    public function getVisite(): ?string
    {
        return $this->visite;
    }

    public function setVisite(string $visite): static
    {
        $this->visite = $visite;

        return $this;
    }

    public function getContratS160(): ?ContratS160
    {
        return $this->contratS160;
    }

    public function setContratS160(?ContratS160 $contratS160): static
    {
        $this->contratS160 = $contratS160;

        return $this;
    }

    public function getRemplacePar(): ?string
    {
        return $this->remplace_par;
    }

    public function setRemplacePar(?string $remplace_par): static
    {
        $this->remplace_par = $remplace_par;

        return $this;
    }

    public function getNumeroIdentification(): ?string
    {
        return $this->numero_identification;
    }

    public function setNumeroIdentification(?string $numero_identification): static
    {
        $this->numero_identification = $numero_identification;

        return $this;
    }
}
