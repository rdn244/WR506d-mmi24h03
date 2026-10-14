<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * Mot de passe demandé pour revenir au compte principal depuis un sous-compte.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class ComptePrincipalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('motDePasse', PasswordType::class, [
            'label' => 'Mot de passe du compte principal',
            'attr' => ['autocomplete' => 'current-password', 'autofocus' => true],
            'constraints' => [new UserPassword(message: 'Mot de passe incorrect.')],
        ]);
    }
}
