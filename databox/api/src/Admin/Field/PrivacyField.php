<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class PrivacyField
{
    public static function new(string $propertyName, string $label = null)
    {
        $choices = [];
        foreach (WorkspaceItemPrivacyInterface::LABELS as $value => $l) {
            $choices[$l] = $value;
        }

        if (empty($choices)) {
            $choices = ['' => ''];
        }

        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }
}
