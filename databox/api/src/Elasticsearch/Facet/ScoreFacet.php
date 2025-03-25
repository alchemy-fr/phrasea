<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;

final class ScoreFacet extends AbstractFacet
{
    protected function getAggregationTitle(): string
    {
        return 'Relevance';
    }

    public function getFieldName(): string
    {
        return '_score';
    }

    public static function getKey(): string
    {
        return '@score';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return null;
    }
}
