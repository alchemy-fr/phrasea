<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrueFalseNullChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [
            'True' => true,
            'False' => false,
            'Unset' => null,
        ];

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }
}
