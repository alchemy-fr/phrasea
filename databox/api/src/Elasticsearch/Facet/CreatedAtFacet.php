<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;

class CreatedAtFacet extends AbstractDateTimeFacet
{
    protected function getAggregationTitle(): string
    {
        return 'Creation date';
    }

    public static function getKey(): string
    {
        return 'createdAt';
    }

    public function getFieldName(): string
    {
        return 'createdAt';
    }

    public function getValueFromAsset(Asset $asset)
    {
        return $asset->getCreatedAt();
    }
}
