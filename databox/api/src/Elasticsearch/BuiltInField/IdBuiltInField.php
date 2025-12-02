<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;
use Elastica\Query;

final class IdBuiltInField extends AbstractBuiltInField
{
    protected function getAggregationTranslationKey(): string
    {
        return 'id';
    }

    public function getFieldName(): string
    {
        return '_id';
    }

    public static function getKey(): string
    {
        return '@id';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getId();
    }

    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    public function createFilterQuery(mixed $value, array $options): ?Query\AbstractQuery
    {
        return new Query\Term(['_id' => $value]);
    }
}
