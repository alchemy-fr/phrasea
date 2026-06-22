<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\DateAttributeType;

abstract class AbstractDateBuiltInField extends AbstractDateTimeBuiltInField
{
    #[\Override]
    protected function getAggregationMinimumInterval(): string
    {
        return 'day';
    }

    #[\Override]
    public function getType(): string
    {
        return DateAttributeType::getName();
    }
}
