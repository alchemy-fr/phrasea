<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\AssetStatusEnum;

class AssetStatusAttributeType extends KeywordAttributeType
{
    public const string NAME = 'asset_status';

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

    public static function normalizeInput(AssetStatusEnum|int|string $value): ?AssetStatusEnum
    {
        if ($value instanceof AssetStatusEnum) {
            return $value;
        }

        if (is_numeric($value)) {
            return AssetStatusEnum::tryFrom((int) $value) ?? AssetStatusEnum::Accepted;
        }

        return null;
    }

    /**
     * @return AssetStatusEnum|null
     */
    #[\Override]
    public function normalizeValue(mixed $value): mixed
    {
        return self::normalizeInput($value);
    }

    #[\Override]
    public function validate(mixed $value): ?array
    {
        if (null === AssetStatusEnum::tryFrom((int) $value)) {
            return ['Invalid asset status value'];
        }

        return null;
    }

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }
}
