<?php

namespace App\Form;

use App\Entity\JourDistrib;
use App\Entity\Pain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class JourDistribType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('pains', EntityType::class, [
                'class' => Pain::class,
                'choice_label' => function (Pain $pain = null) {
                    return $pain->getNom() . " " . $pain->getPoid();
                },
                'choice_value' => 'id',
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('date', DateType::class, [
                'label' => 'Date de Disribution ',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control input-inline js-datepicker'],
                'format' => 'dd/MM/yyyy',
                'html5' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => JourDistrib::class,
        ]);
    }
}
