<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\IdField as BaseIdField;

final class IdField
{
    public static function new(string $propertyName = 'id', $label = null): BaseIdField
    {
        if (null === $label && $propertyName === 'id') {
            $label = 'ID';
        }

        return BaseIdField::new($propertyName, $label)
            ->setTemplatePath('@AlchemyAdmin/list/id.html.twig');
    }
}
