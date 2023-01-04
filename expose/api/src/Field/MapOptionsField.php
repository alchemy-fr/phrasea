<?php

declare(strict_types=1);

namespace App\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use App\Form\MapOptionsType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class MapOptionsField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null)
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
//            ->setTemplateName('crud/field/integer')
            ->setFormType(MapOptionsType::class);
    }

}
