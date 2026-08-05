<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\NumberAttributeType;
use App\Entity\Core\Asset;

final class ScoreBuiltInAttribute extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'relevance';
    }

    #[\Override]
    public function getType(): string
    {
        return NumberAttributeType::NAME;
    }

    public static function getName(): string
    {
        return '_score';
    }

    public static function getKey(): string
    {
        return '@score';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return null;
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }

    #[\Override]
    public function isSearchable(): bool
    {
        return false;
    }
}
