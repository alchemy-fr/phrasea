<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\IdAttributeType;
use App\Entity\Core\Asset;
use Elastica\Query;

final class IdBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'id';
    }

    public static function getName(): string
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

    #[\Override]
    public function getType(): string
    {
        return IdAttributeType::getName();
    }

    public function createFilterQuery(mixed $value, array $options): Query\AbstractQuery
    {
        return new Query\Term(['_id' => $value]);
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
