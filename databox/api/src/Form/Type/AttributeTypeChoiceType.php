<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Attribute\AttributeTypeRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeTypeChoiceType extends AbstractType
{
    private AttributeTypeRegistry $typeRegistry;

    public function __construct(AttributeTypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
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
