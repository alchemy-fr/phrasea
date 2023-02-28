<?php

namespace Alchemy\AdminBundle\Field\Acl;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class UserTypeChoiceField
{
    public function create(string $propertyName, ?string $label = null)
    {
        $choices = [];
        foreach (AccessControlEntry::USER_TYPES as $name => $code) {
            $choices[$name] = $code;
        }

        return ChoiceField::new($propertyName, $label)
            ->setChoices($choices)
            ;
    }
}
