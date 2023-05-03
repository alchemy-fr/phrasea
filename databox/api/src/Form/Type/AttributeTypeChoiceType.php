<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Attribute\AttributeTypeRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeTypeChoiceType extends AbstractType
{
    public function __construct(private readonly AttributeTypeRegistry $typeRegistry)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [];
        foreach ($this->typeRegistry->getTypes() as $name => $type) {
            $choices[$name] = $name;
        }

        $resolver->setDefaults([
            'choices' => $choices,
            'placeholder' => 'Choose...',
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
