<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\DateAttributeType;

abstract class AbstractDateBuiltInAttribute extends AbstractDateTimeBuiltInAttribute
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
