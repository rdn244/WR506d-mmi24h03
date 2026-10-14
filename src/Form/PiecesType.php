<?php

namespace App\Form;

use App\Enum\TypePiece;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Sélection des pièces d'un logement : pièces prédéfinies + pièces personnalisées.
 *
 * Données : ['types' => list<TypePiece>, 'personnalisees' => list<string>]
 *
 * @extends AbstractType<array<string, mixed>>
 */
class PiecesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('types', EnumType::class, [
                'label' => false,
                'class' => TypePiece::class,
                'choices' => TypePiece::predefinies(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => static fn (TypePiece $type) => $type->libelle(),
            ])
            ->add('personnalisees', CollectionType::class, [
                'label' => false,
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => 'Nom de la pièce',
                    'attr' => ['placeholder' => 'Ex : Buanderie'],
                    'constraints' => [new Length(max: 100)],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Callback(static function (array $donnees, ExecutionContextInterface $context): void {
                    $personnalisees = array_filter($donnees['personnalisees'] ?? [], static fn (?string $nom) => null !== $nom && '' !== trim($nom));
                    if ([] === ($donnees['types'] ?? []) && [] === $personnalisees) {
                        $context->buildViolation('Sélectionne au moins une pièce.')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
