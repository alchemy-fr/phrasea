<?php

declare(strict_types=1);

namespace App\Elasticsearch;

interface ESFacetInterface
{
    public const string TYPE_TEXT = 'text';
    public const string TYPE_BOOLEAN = 'boolean';
    public const string TYPE_DATE_RANGE = 'date_range';
    public const string TYPE_GEO_DISTANCE = 'geo_distance';
    public const string TYPE_TAGS = 'tags';
}
