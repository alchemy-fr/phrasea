<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Entity\Core\RenditionRule;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class RenditionRuleObjectTypeChoiceField
{
    public static function new(string $propertyName, ?string $label = null): ChoiceField
    {
        $choices = [];
        foreach ([
                     'Collection' => RenditionRule::TYPE_COLLECTION,
                     'Workspace' => RenditionRule::TYPE_WORKSPACE,
                 ] as $name => $code) {
            $choices[$name] = $code;
        }

        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }
}
