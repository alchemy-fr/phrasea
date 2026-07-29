<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;

final class FileTypeBuiltInAttribute extends AbstractBuiltInAttribute
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

    #[\Override]
    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return true;
    }
}
