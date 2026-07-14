<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Elasticsearch\AQL\ConditionOperatorEnum;
use Elastica\Query;

interface CustomFilterQueryBuiltInAttributeInterface extends BuiltInAttributeInterface
{
    public function createFilterQuery(mixed $value, ConditionOperatorEnum $operator, array $options): Query\AbstractQuery;
}
