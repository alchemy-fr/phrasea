<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;

class EditedAtFacet extends AbstractDateTimeFacet
{
    protected function getAggregationTranslationKey(): string
    {
        return 'modification_date';
    }

    public static function getKey(): string
    {
        return '@editedAt';
    }

    public function getFieldName(): string
    {
        return 'editedAt';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getEditedAt();
    }
}
