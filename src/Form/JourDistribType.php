<?php

namespace App\Form;

use App\Entity\JourDistrib;
use App\Entity\Pain;
use App\Entity\Boulanger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class JourDistribType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('closed', CheckboxType::class, [
            'label'    => 'Fermer la vente',
            'required' => false,
        ]);
        if ($options['edit']){
            $builder
                ->add('pains', EntityType::class, [
                    'class' => Pain::class,
                    'choice_label' => function (Pain $pain = null) {
                        return $pain->getNom() . " - " . $pain->getPoid() . "kg";
                    },
                    'choice_value' => 'id',
                    'multiple' => true,
                    'expanded' => true,
                ]);
        }
        else {
            $builder
                ->add('pains', EntityType::class, [
                    'class' => Pain::class,
                    'choice_label' => function (Pain $pain = null) {
                        return $pain->getNom() . " - " . $pain->getPoid() . "kg";
                    },
                    'choice_value' => 'id',
                    'multiple' => true,
                    'expanded' => true,
                    'choice_attr' => function($val, $key, $index) {
                        return array('checked' => true);
                    },
                ]);
        }
        $builder
            ->add('date', DateType::class, [
                'label' => 'Date de Disribution ',
                'widget' => 'single_text',
                // 'attr' => ['class' => 'ui-datepicker'],
                // 'format' => 'dd/MM/yyyy',
                // 'html5' => false,
                // 'model_timezone' => 'Europe/Paris',
            ])
            ->add('total', NumberType::class,[
                'label' => 'Poid total de la fournée (en kg)'
            ])
            ->add('boulanger', EntityType::class,[
                'class' => Boulanger::class,
                'choice_label' => 'prenom'
            ])
            ->add('commentaire')
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => JourDistrib::class,
            'edit' => false,
        ]);
    }
}
