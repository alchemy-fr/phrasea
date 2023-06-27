<?php

namespace Alchemy\OAuthServerBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class AllowedScopesChoiceField
{
    private array $choices = [];

    public function __construct(array $scopes)
    {
        foreach ($scopes as $scope) {
            $this->choices[$scope] = $scope;
        }
    }

    public function create(string $propertyName, string $label = null)
    {
        return ChoiceField::new($propertyName, $label)
            ->setChoices($this->choices)
            ->allowMultipleChoices()
            ->renderExpanded(true)
        ;
    }
}
