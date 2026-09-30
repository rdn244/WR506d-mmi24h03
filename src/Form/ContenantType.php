<?php

namespace App\Form;

use App\Entity\Contenant;
use App\Enum\TypeContenant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContenantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du contenant',
                'attr' => ['placeholder' => 'Ex : Armoire'],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Type',
                'class' => TypeContenant::class,
                'expanded' => true,
                'choice_label' => static fn (TypeContenant $type) => $type->icone().' '.$type->libelle(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contenant::class,
        ]);
    }
}
