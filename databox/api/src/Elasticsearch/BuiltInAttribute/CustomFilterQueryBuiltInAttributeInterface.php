<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Elasticsearch\AQL\ConditionOperatorEnum;
use Elastica\Query;

interface CustomFilterQueryBuiltInAttributeInterface extends BuiltInAttributeInterface
{
    public function createFilterQuery(mixed $value, ConditionOperatorEnum $operator, array $options): Query\AbstractQuery;

    /**
     * Whether the query contributes to scoring and must be added
     * as a "must" clause instead of a "filter" one.
     */
    public function isScoreBasedQuery(): bool;
}
