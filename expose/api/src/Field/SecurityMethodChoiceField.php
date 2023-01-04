<?php

declare(strict_types=1);

namespace App\Field;


use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use App\Form\SecurityMethodChoiceType;

class SecurityMethodChoiceField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null)
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
//            ->setTemplateName('crud/field/integer')
            ->setFormType(SecurityMethodChoiceType::class);
    }

}
