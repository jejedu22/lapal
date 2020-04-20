<?php

namespace App\Form;

use App\Entity\LigneCommande;
use App\Entity\Pain;
use App\Repository\JourDistribRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;

class LigneCommandeType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('pain', EntityType::class, [
                'class' => Pain::class,
                'choice_label' => function (Pain $pain = null) {
                    return $pain->getNom() . " - " . $pain->getPoid() . " kg";
                },
                'choice_value' => 'id',
                'choices' => $options['pains'],
                'label' => false,
                'required' => true,
            ])
            ->add('quantite', IntegerType::class,[
                'required' => true,
                'attr' => array('min' => 1, 'max' => 5),
            ])


        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => LigneCommande::class,
            'pains' => Pains::class,
        ]);
    }
}
