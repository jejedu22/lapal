<?php

namespace App\Form;

use App\Entity\Pain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class PainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', null, ['label' => 'Nom'])
            ->add('poid', NumberType::class, [
                'label' => 'Poids (kg)',
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.05'],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (€)',
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.05'],
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Proposé à la vente (décocher pour archiver)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pain::class,
        ]);
    }
}
