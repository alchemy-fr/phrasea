<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;

final class FileTypeBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'file_type';
    }

    public static function getName(): string
    {
        return 'fileType';
    }

    public static function getKey(): string
    {
        return '@type';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileType();
    }

    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    public function isFacet(): bool
    {
        return true;
    }
}
