<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Integration\IntegrationRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class IntegrationChoiceField
{
    private IntegrationRegistry $integrationRegistry;

    public function __construct(IntegrationRegistry $integrationRegistry)
    {
        $this->integrationRegistry = $integrationRegistry;
    }

    public function create(string $propertyName, ?string $label = null)
    {
        $choices = [];
        foreach ($this->integrationRegistry->getIntegrations() as $type) {
            $choices[$type::getTitle()] = $type::getName();
        }

        if (empty($choices)) {
            $choices = ['' => ''];
        }

        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }
}
