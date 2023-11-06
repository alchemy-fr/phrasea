<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Publication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecurityMethodChoiceType extends AbstractType
{
    private array $choices = [
        Publication::SECURITY_METHOD_PASSWORD,
        Publication::SECURITY_METHOD_AUTHENTICATION,
    ];

    public function __construct()
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];
        foreach ($this->choices as $choice) {
            $choices[$choice] = $choice;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}
