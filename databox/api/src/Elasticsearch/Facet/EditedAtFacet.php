<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;

class EditedAtFacet extends AbstractDateTimeFacet
{
    protected function getAggregationTitle(): string
    {
        return 'Modification date';
    }

    public static function getKey(): string
    {
        return '@editedAt';
    }

    public function getFieldName(): string
    {
        return 'editedAt';
    }

    public function getValueFromAsset(Asset $asset)
    {
        return $asset->getEditedAt();
    }
}
