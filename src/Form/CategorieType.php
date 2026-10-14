<?php

namespace App\Form;

use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategorieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex : Tenues de sport', 'maxlength' => 60],
            ])
            ->add('icone', ChoiceType::class, [
                'label' => 'Icône',
                'expanded' => true,
                'choices' => array_combine(Categorie::ICONES, Categorie::ICONES),
                // Puces sans texte : l'icône seule, nommée pour les lecteurs d'écran
                'choice_label' => static fn (string $icone) => '',
                'choice_attr' => static fn (string $icone) => ['data-icone' => $icone, 'aria-label' => self::libelleIcone($icone)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Categorie::class,
        ]);
    }

    private static function libelleIcone(string $icone): string
    {
        return ucfirst(str_replace(['ph:', '-duotone', '-'], ['', '', ' '], $icone));
    }
}
