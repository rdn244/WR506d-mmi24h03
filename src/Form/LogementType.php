<?php

namespace App\Form;

use App\Entity\Logement;
use App\Enum\TypeLogement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du logement',
                'attr' => ['placeholder' => 'Ex : Maison de Troyes'],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Type de logement',
                'class' => TypeLogement::class,
                'expanded' => true,
                'choice_label' => static fn (TypeLogement $type) => $type->icone().' '.$type->libelle(),
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Ville, code postal', 'autocomplete' => 'address-level2'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Logement::class,
        ]);
    }
}
