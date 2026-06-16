<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\WorkspaceItemPrivacyInterface;

class PrivacyAttributeType extends KeywordAttributeType
{
    public const string NAME = 'privacy';

    public function isLocaleAware(): bool
    {
        return false;
    }

    public function supportsSuggest(): bool
    {
        return false;
    }

    public function normalizeValue(mixed $value): mixed
    {
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return parent::normalizeValue($value);
    }

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

    public function supportsAggregation(): bool
    {
        return true;
    }
}
