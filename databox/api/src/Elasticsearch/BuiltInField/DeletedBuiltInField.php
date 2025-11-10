<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\BooleanAttributeType;
use App\Entity\Core\Asset;
use Elastica\Query;

final class DeletedBuiltInField extends AbstractBuiltInField
{
    protected function getAggregationTranslationKey(): string
    {
        return 'deleted';
    }

    public function getFieldName(): string
    {
        return 'deleted';
    }

    public static function getKey(): string
    {
        return '@deleted';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->isDeleted();
    }

    public function getType(): string
    {
        return BooleanAttributeType::getName();
    }

    public function createFilterQuery(mixed $value): ?Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();
        if (null === $value) {
            return $boolQuery;
        }

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $boolQuery->addShould(new Query\Term(['deleted' => true]));
            $boolQuery->addShould(new Query\Term(['collectionDeleted' => true]));
        } else {
            $boolQuery->addMust(new Query\Term(['deleted' => false]));
            $boolQuery->addMust(new Query\Term(['collectionDeleted' => false]));
        }

        return $boolQuery;
    }
}
