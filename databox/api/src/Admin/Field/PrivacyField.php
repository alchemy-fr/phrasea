<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Entity\Core\WorkspaceItemPrivacyInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PrivacyField
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function create(string $propertyName, ?string $label = null): ChoiceField
    {
        $choices = [];
        foreach (WorkspaceItemPrivacyInterface::KEYS as $value => $l) {
            $choices[$this->translator->trans(sprintf('privacy.%s', $l))] = $value;
        }

        return ChoiceField::new($propertyName, $label)->setChoices($choices);
    }
}
