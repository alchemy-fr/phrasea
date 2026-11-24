<?php

namespace Alchemy\AdminBundle\Field\Acl;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class ObjectTypeChoiceField
{
    private readonly ObjectMapping $objectMapping;

    public function __construct(ObjectMapping $objectMapping)
    {
        $this->objectMapping = $objectMapping;
    }

    public function create(string $propertyName, ?string $label = null)
    {
        return ChoiceField::new($propertyName, $label)
            ->setChoices($this->getChoices())
        ;
    }

    /**
     * @return array<string, string>
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach ($this->objectMapping->getObjectTypes() as $name) {
            $choices[$name] = $name;
        }

        return $choices;
    }
}
