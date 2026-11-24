<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PrivacyField
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    private function getChoices(): array
    {
        $choices = [];
        foreach (WorkspaceItemPrivacyInterface::KEYS as $value => $l) {
            $choices[$this->translator->trans(sprintf('privacy.%s', $l))] = $value;
        }

        return $choices;
    }

    public function create(string $propertyName, ?string $label = null): ChoiceField
    {
        return ChoiceField::new($propertyName, $label)->setChoices($this->getChoices());
    }

    public function createFilter(string $propertyName = 'privacy', ?string $label = null): ChoiceFilter
    {
        return ChoiceFilter::new($propertyName, $label)->setChoices($this->getChoices());
    }
}
