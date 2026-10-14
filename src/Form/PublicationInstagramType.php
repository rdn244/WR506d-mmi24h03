<?php

namespace App\Form;

use App\Enum\FormatPublication;
use App\Instagram\DemandePublication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<DemandePublication>
 */
class PublicationInstagramType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('format', EnumType::class, [
                'label' => 'Format',
                'class' => FormatPublication::class,
                'expanded' => true,
                'choice_label' => static fn (FormatPublication $format) => $format->libelle(),
                'choice_attr' => static fn (FormatPublication $format) => ['data-icone' => $format->icone()],
            ])
            ->add('legende', TextareaType::class, [
                'label' => 'Légende',
                'required' => false,
                'help' => 'La légende n\'apparaît pas sur une story.',
                'attr' => ['rows' => 3, 'maxlength' => 2200],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandePublication::class,
        ]);
    }
}
