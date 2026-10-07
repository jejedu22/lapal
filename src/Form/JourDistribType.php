<?php

namespace App\Form;

use App\Entity\JourDistrib;
use App\Entity\Pain;
use App\Entity\Boulanger;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class JourDistribType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('closed', CheckboxType::class, [
            'label'    => 'Commandes fermées (les clients ne peuvent plus commander)',
            'required' => false,
        ]);

        $pains = [
            'class' => Pain::class,
            'label' => 'Pains proposés',
            'choice_label' => function (Pain $pain) {
                return $pain->getLibelle() . ($pain->getActif() ? '' : ' (archivé)');
            },
            'choice_value' => 'id',
            'multiple' => true,
            'expanded' => true,
            'query_builder' => function (EntityRepository $er) use ($options) {
                $qb = $er->createQueryBuilder('p')->orderBy('p.position', \SortDirection::Ascending)->addOrderBy('p.nom', \SortDirection::Ascending);
                // un nouveau jour ne propose que les pains actifs ;
                // en modification on garde aussi les pains archivés déjà cochés
                if (!$options['edit']) {
                    $qb->where('p.actif = true');
                }

                return $qb;
            },
        ];
        if (!$options['edit']) {
            $pains['choice_attr'] = function () {
                return ['checked' => true];
            };
        }
        $builder->add('pains', EntityType::class, $pains);

        $builder
            ->add('date', DateType::class, [
                'label' => 'Date de distribution',
                'widget' => 'single_text',
            ])
            ->add('total', NumberType::class, [
                'label' => 'Poids total de la fournée (kg)',
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.5'],
            ])
            ->add('boulanger', EntityType::class, [
                'class' => Boulanger::class,
                'choice_label' => function (Boulanger $boulanger) {
                    return $boulanger->getPrenom() . ' ' . $boulanger->getNom();
                },
            ])
            ->add('commentaire', null, [
                'label' => 'Lieu / information pour les clients',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : au local associatif, de 17 h à 19 h'],
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JourDistrib::class,
            'edit' => false,
        ]);
    }
}
