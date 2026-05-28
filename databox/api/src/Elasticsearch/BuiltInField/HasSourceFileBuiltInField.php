<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\BooleanAttributeType;
use App\Entity\Core\Asset;

final class HasSourceFileBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'has_source_file';
    }

    public static function getName(): string
    {
        return 'hasSourceFile';
    }

    public static function getKey(): string
    {
        return '@hasSource';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return null !== $asset->getSource();
    }

    public function getType(): string
    {
        return BooleanAttributeType::getName();
    }

    public function isFacet(): bool
    {
        return true;
    }
}
