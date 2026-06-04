<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\NumberAttributeType;
use App\Entity\Core\Asset;

final class FileSizeBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'file_size';
    }

    public static function getName(): string
    {
        return 'fileSize';
    }

    public static function getKey(): string
    {
        return '@size';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileSize();
    }

    public function getType(): string
    {
        return NumberAttributeType::getName();
    }

    public function isFacet(): bool
    {
        return false;
    }
}
