<?php

namespace Alchemy\AdminBundle\Field\Acl;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class ObjectTypeChoiceField
{
    private ObjectMapping $objectMapping;

    public function __construct(ObjectMapping $objectMapping)
    {
        $this->objectMapping = $objectMapping;
    }

    public function create(string $propertyName, ?string $label = null)
    {
        $choices = [];
        foreach ($this->objectMapping->getObjectTypes() as $name) {
            $choices[$name] = $name;
        }

        return ChoiceField::new($propertyName, $label)
            ->setChoices($choices)
        ;
    }
}
