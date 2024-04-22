<?php

namespace Alchemy\AdminBundle\Field\Acl;

use Alchemy\AclBundle\Security\PermissionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class PermissionsChoiceField
{
    public function create(string $propertyName, ?string $label = null)
    {
        $choices = [];
        foreach (PermissionInterface::PERMISSIONS as $name => $permission) {
            $choices[$name] = $permission;
        }

        return ChoiceField::new($propertyName, $label)
            ->setChoices($choices)
            ->allowMultipleChoices()
            ->renderExpanded(true)
        ;
    }
}
