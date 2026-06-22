<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;

final class FileExtensionBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'file_extension';
    }

    public static function getName(): string
    {
        return 'fileExtension';
    }

    public static function getKey(): string
    {
        return '@extension';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileExtension();
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
