<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\Pain;
use App\Entity\LigneCommande;
use App\Entity\JourDistrib;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

use App\Repository\JourDistribRepository;

class CommandeType extends AbstractType
{    
    private $jourDistribRepository;

    public function __construct(JourDistribRepository $jourDistribRepository)
    {
        $this->jourDistribRepository = $jourDistribRepository;
    }

    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['client']) {
            // Parcours client : les quantités par pain sont saisies à part (quantites[idPain])
            $builder
                ->add('prenom', TextType::class, [
                    'label' => 'Prénom',
                    'required' => true,
                    'attr' => ['autocomplete' => 'given-name'],
                    'empty_data' => '',
                    'constraints' => [new NotBlank(message: 'Indiquez votre prénom.'), new Length(max: 255)],
                ])
                ->add('nom', TextType::class, [
                    'label' => 'Nom',
                    'required' => true,
                    'attr' => ['autocomplete' => 'family-name'],
                    'empty_data' => '',
                    'constraints' => [new NotBlank(message: 'Indiquez votre nom.'), new Length(max: 255)],
                ])
                ->add('commentaire', TextareaType::class, [
                    'label' => 'Commentaire pour le boulanger',
                    'required' => false,
                    'attr' => ['rows' => 2, 'placeholder' => 'Ex. : bien cuit, je passe en fin de distribution…'],
                ]);

            return;
        }

        $builder
            ->add('jourDistrib', EntityType::class, [
                'class' => JourDistrib::class,
                'choice_label' => 'id',
                'choices' => [$options['jourDistrib']],
                'attr' => ['class' => 'd-none'],
                'label' => false,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom : ',
                'required' => true,
                'data' => $options['lastNom']
                ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom : ',
                'required' => true,
                'data' => $options['lastPrenom']
            ])
            ->add('ligneCommandes', CollectionType::class, [
                'entry_type'   => LigneCommandeType::class,
                'entry_options' => ['label' => false, 'pains' => $options['pains']],
                'label' => false,
                'allow_add'    => true,
                'allow_delete' => true,
                'prototype'    => true,
                'required'     => true,
                'by_reference' => false,
                'delete_empty' => true,
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
            ])
            ->add('livree', HiddenType::class, [
                'data' => 0,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'idJourDistrib' => 1,
            'pains' => Pain::class,
            'jourDistrib' => JourDistrib::class,
            'lastNom' => null,
            'lastPrenom' => null,
            'client' => false,
        ]);
        $resolver->setAllowedTypes('client', 'bool');
        $resolver->setAllowedTypes('idJourDistrib', 'int');
    }
}
