<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\DateAttributeType;

abstract class AbstractDateBuiltInField extends AbstractDateTimeBuiltInField
{
    protected function getAggregationMinimumInterval(): string
    {
        return 'day';
    }

    public function getType(): string
    {
        return DateAttributeType::getName();
    }
}
