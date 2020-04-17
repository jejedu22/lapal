<?php

namespace App\Form;

use App\Entity\LigneCommande;
use App\Entity\Pain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class LigneCommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('pain', EntityType::class, [
                'class' => Pain::class,
                'choice_label' => function (Pain $pain = null) {
                    return $pain->getNom() . " " . $pain->getPoid();
                },
                'choice_value' => 'id',
            ])
            ->add('quantite')
            // ->add('commande')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => LigneCommande::class,
        ]);
    }
}
