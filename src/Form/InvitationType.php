<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Invitation d'un compte lié par son email.
 */
class InvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Email du compte à lier',
            'attr' => ['placeholder' => 'son@email.com'],
            'constraints' => [
                new NotBlank(message: 'Renseigne un email.'),
                new Email(message: 'Cet email n\'est pas valide.'),
            ],
        ]);
    }
}
