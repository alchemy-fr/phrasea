<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\WorkspaceItemPrivacyInterface;

class PrivacyAttributeType extends KeywordAttributeType
{
    public const string NAME = 'privacy';

    #[\Override]
    public function isLocaleAware(): bool
    {
        return false;
    }

    #[\Override]
    public function supportsSuggest(): bool
    {
        return false;
    }

    #[\Override]
    public function normalizeValue(mixed $value): mixed
    {
        if (is_numeric($value)) {
            $int = (int) $value;
            if (in_array($int, [
                WorkspaceItemPrivacyInterface::SECRET,
                WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
                WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE,
                WorkspaceItemPrivacyInterface::PRIVATE,
                WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS,
                WorkspaceItemPrivacyInterface::PUBLIC,
            ], true)) {
                return $int;
            }
        }

        return parent::normalizeValue($value);
    }

    #[\Override]
    public function validate(mixed $value): ?array
    {
        if (!in_array($value, [
            WorkspaceItemPrivacyInterface::SECRET,
            WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE,
            WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE,
            WorkspaceItemPrivacyInterface::PRIVATE,
            WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS,
            WorkspaceItemPrivacyInterface::PUBLIC,
        ], true)) {
            return ['Invalid privacy value'];
        }

        return null;
    }

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }
}
