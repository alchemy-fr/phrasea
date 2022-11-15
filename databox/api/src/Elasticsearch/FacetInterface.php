<?php

declare(strict_types=1);

namespace App\Elasticsearch;

interface FacetInterface
{
    public const TYPE_STRING = 'string';
    public const TYPE_DATE_RANGE = 'date_range';
}
