<?php

namespace App\Form;

use App\Entity\Look;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;

class LookType extends AbstractType
{
    /**
     * Formats et poids acceptés par Instagram.
     */
    public const TYPES_PHOTO = ['image/jpeg', 'image/png'];
    public const POIDS_PHOTO = '8M';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                // Le fichier est rangé par StockagePhotos, l'entité ne garde que son nom
                'mapped' => false,
                'attr' => ['accept' => implode(',', self::TYPES_PHOTO)],
                'constraints' => [
                    new NotNull(message: 'Ajoute une photo de ton look.'),
                    new Image(
                        maxSize: self::POIDS_PHOTO,
                        mimeTypes: self::TYPES_PHOTO,
                        maxSizeMessage: 'La photo dépasse {{ limit }} {{ suffix }}.',
                        mimeTypesMessage: 'La photo doit être au format JPEG ou PNG.',
                    ),
                ],
            ])
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex : Manteau bordeaux et jean brut', 'maxlength' => 80],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'help' => 'Reprise comme légende si tu publies le look sur Instagram.',
                'attr' => ['rows' => 3, 'placeholder' => 'Où, quand, avec quoi…'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Look::class,
        ]);
    }
}
