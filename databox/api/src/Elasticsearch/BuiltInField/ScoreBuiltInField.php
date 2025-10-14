<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\NumberAttributeType;
use App\Entity\Core\Asset;

final class ScoreBuiltInField extends AbstractBuiltInField
{
    protected function getAggregationTranslationKey(): string
    {
        return 'relevance';
    }

    public function getType(): string
    {
        return NumberAttributeType::NAME;
    }

    public function getFieldName(): string
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
}
