<?php

declare(strict_types=1);

namespace App\Elasticsearch;

interface ESFacetInterface
{
    public const TYPE_TEXT = 'text';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE_RANGE = 'date_range';
    public const TYPE_GEO_DISTANCE = 'geo_distance';
}
