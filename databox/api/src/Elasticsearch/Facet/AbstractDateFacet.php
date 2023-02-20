<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\DateAttributeType;

abstract class AbstractDateFacet extends AbstractDateTimeFacet
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
