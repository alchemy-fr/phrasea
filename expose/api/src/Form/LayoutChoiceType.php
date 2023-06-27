<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LayoutChoiceType extends AbstractType
{
    public function __construct(private readonly array $choices)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [
            'None (inherit from profile)' => null,
        ];
        foreach ($this->choices as $key => $choice) {
            $choices[$choice] = $key;
        }

        $resolver->setDefaults([
            'choices' => $choices,
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
