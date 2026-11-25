<?php

namespace Alchemy\AdminBundle\Filter;

use Alchemy\AdminBundle\Utils\UserChoiceLoader;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FiltersFormType;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class UserChoiceFilter
{
    public function __construct(
        private UserChoiceLoader $userChoiceLoader,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * A choice among the users, in which an ID missing from the list can also be typed. Without
     * the user list (identity provider unavailable), an exact match on the typed ID.
     */
    public function createFilter(string $propertyName, ?string $label = null): FilterInterface
    {
        $users = $this->userChoiceLoader->getUsers();
        if (empty($users)) {
            return TextFilter::new($propertyName, $label)
                ->setFormTypeOption('comparison_type_options.type', 'choice');
        }

        $requestedId = $this->getRequestedId($propertyName);
        if (null !== $requestedId && !isset($users[$requestedId])) {
            $users[$requestedId] = $requestedId;
        }

        $choices = [];
        foreach ($users as $id => $name) {
            $choices[$name] = (string) $id;
        }

        return ChoiceFilter::new($propertyName, $label)
            ->setChoices($choices)
            ->setFormTypeOption('value_type_options.attr', [
                'data-ea-widget' => 'ea-autocomplete',
                'data-ea-autocomplete-allow-item-create' => 'true',
            ]);
    }

    private function getRequestedId(string $propertyName): ?string
    {
        $filters = $this->requestStack->getCurrentRequest()?->query->all(EA::FILTERS) ?? [];
        $value = $filters[str_replace('.', FiltersFormType::EMBEDDED_PROPERTY_SEPARATOR, $propertyName)]['value'] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
