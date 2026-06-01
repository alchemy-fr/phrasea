<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;

final class FileNameBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'file_name';
    }

    public static function getName(): string
    {
        return 'fileName';
    }

    public static function getKey(): string
    {
        return '@filename';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileName();
    }

    public function getType(): string
    {
        return TextAttributeType::getName();
    }

    public function isFacet(): bool
    {
        return false;
    }
}
