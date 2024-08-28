<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Integration\IntegrationRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

final readonly class IntegrationChoiceField
{
    public function __construct(private IntegrationRegistry $integrationRegistry)
    {
    }

    private function getChoices(): array
    {
        $choices = [];
        foreach ($this->integrationRegistry->getIntegrations() as $type) {
            $choices[$type::getTitle()] = $type::getName();
        }

        return $choices ?: ['' => ''];
    }

    public function create(string $propertyName, ?string $label = null): ChoiceField
    {
        return ChoiceField::new($propertyName, $label)->setChoices($this->getChoices());
    }

    public function createFilter(string $propertyName, ?string $label = null): ChoiceFilter
    {
        return ChoiceFilter::new($propertyName, $label)->setChoices($this->getChoices());
    }
}
