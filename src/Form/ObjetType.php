<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Contenant;
use App\Entity\Objet;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fiche d'un objet, pré-remplie par le scan IA (maquette 15).
 */
class ObjetType extends AbstractType
{
    /**
     * Champs que l'IA peut remplir, avec leur libellé et un exemple.
     */
    public const CHAMPS_IA = [
        'nom' => ['Nom', 'Ex : Manteau bordeaux'],
        'marque' => ['Marque', 'Ex : Sandro Paris'],
        'type' => ['Type', 'Ex : Manteau'],
        'matiere' => ['Matière', 'Ex : Laine mélangée'],
        'couleur' => ['Couleur dominante', 'Ex : Bordeaux'],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Photo réduite dans le navigateur et transmise en base64 (data URL), vérifiée par le contrôleur
        if ($options['avec_photo']) {
            $builder->add('photo', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['data-scan-target' => 'photo'],
            ]);
        }

        foreach (self::CHAMPS_IA as $champ => [$libelle, $exemple]) {
            $builder->add($champ, TextType::class, [
                'label' => $libelle,
                'required' => 'nom' === $champ,
                // Une saisie manuelle n'est plus écrasée par l'analyse
                'attr' => ['placeholder' => $exemple, 'data-action' => 'input->scan#modifie'],
            ]);
        }

        $builder
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Categorie::class,
                'choices' => $options['categories'],
                'choice_label' => 'nom',
                'choice_attr' => static fn (Categorie $categorie) => ['data-icone' => $categorie->getIcone(), 'data-action' => 'change->scan#modifie'],
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
            ])
            ->add('contenant', EntityType::class, [
                'label' => 'Rangement',
                'class' => Contenant::class,
                'choices' => $options['contenants'],
                'choice_label' => static fn (Contenant $contenant) => $contenant->getPiece()->getNom().' › '.$contenant->getNom(),
                'required' => false,
                'placeholder' => 'Pas encore rangé',
            ])
            ->add('personnel', CheckboxType::class, [
                'label' => 'Objet personnel',
                'help' => 'Visible par toi seul : ni tes sous-comptes ni tes proches ne le voient.',
                'required' => false,
            ])
        ;

        if ($options['avec_photo']) {
            $builder->add('modeleIa', HiddenType::class, [
                'required' => false,
                'attr' => ['data-scan-target' => 'modele'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Objet::class,
            'avec_photo' => true,
        ]);
        // Rangements et catégories proposés : uniquement ceux du propriétaire (et les catégories prédéfinies)
        $resolver->setRequired(['contenants', 'categories']);
        $resolver->setAllowedTypes('contenants', 'array');
        $resolver->setAllowedTypes('categories', 'array');
        $resolver->setAllowedTypes('avec_photo', 'bool');
    }
}
