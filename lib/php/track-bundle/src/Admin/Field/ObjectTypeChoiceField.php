<?php

namespace Alchemy\TrackBundle\Admin\Field;

use Alchemy\CoreBundle\Mapping\ObjectMapping;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final readonly class ObjectTypeChoiceField
{
    public function __construct(private ObjectMapping $objectMapping)
    {
    }

    public function create(string $propertyName, ?string $label = null): ChoiceField
    {
        $choices = [];
        foreach ($this->objectMapping->getObjectTypes() as $objectType) {
            $choices[$this->objectMapping->getClassName($objectType)] = $objectType;
        }

        if (empty($choices)) {
            $choices = ['' => ''];
        }

        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }
}
