<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class ArrayObjectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, string $label = null)
    {
        return ArrayField::new($propertyName, $label)
            ->setTemplatePath('@AlchemyAdmin/fields/array_object.html.twig')
        ;
    }
}
