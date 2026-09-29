<?php

namespace App\Form;

use App\Entity\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', HiddenType::class,[])
        ;
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $settings = $event->getData();
            $form = $event->getForm();

            // checks if the Product object is "new"
            // If no data is passed to the form, the data is "null".
            // This should be considered a new "Product"
            if (!$settings || 'color' === $settings->getName()) {
                $form->add('value', ChoiceType::class, [
                    'choices'  => [
                        'Bleu' => 'blue',
                        'Cyan' => 'cyan',
                        'Gris' => 'gray',
                        'Gris Foncé' => 'gray-dark',
                        'Indigo' => 'indigo',
                        'Jaune' => 'yellow',
                        'Orange' => 'orange',
                        'Rose' => 'pink',
                        'Rouge' => 'red',
                        'Turquoise' => 'teal',
                        'Vert' => 'green',
                        'Violet' => 'purple',
                    ],
                    'multiple'=>false,
                    'expanded'=>true,
                    'choice_attr' => function($choice, $key, $value) {
                        return ['class' => 'color-'.strtolower($value)];
                    },
                    'placeholder' => false
                ]);
            }
            elseif ('logo' === $settings->getName()) {
                $form->add('value', FileType::class, [
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new File([
                            'maxSize' => '100k',
                            'mimeTypes' => [
                                'image/png',
                            ],
                            'mimeTypesMessage' => 'Télécharger un fichier PNG valide',
                        ])
                    ],
                    'label' => 'Choisir un fichier',
                ]);
            }
            elseif ('contact_email' === $settings->getName()) {
                $form->add('value', EmailType::class, [
                    'help' => 'Adresse affichée via l’icône enveloppe en haut à droite.',
                    'mapped' => false,
                    'data' => $settings->getValue(),
                    'required' => false,
            ]);
            }
            elseif ('welcome_text' === $settings->getName()) {
                $form->add('value', TextareaType::class, [
                    'required' => false,
                    'attr' => ['rows' => 3],
                ]);
            }
            else {
                $form->add('value');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
        ]);
    }
}
