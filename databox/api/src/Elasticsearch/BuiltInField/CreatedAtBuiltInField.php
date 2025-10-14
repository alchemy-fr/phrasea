<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Entity\Core\Asset;

class CreatedAtBuiltInField extends AbstractDateTimeBuiltInField
{
    protected function getAggregationTranslationKey(): string
    {
        return 'created_at';
    }

    public static function getKey(): string
    {
        return '@createdAt';
    }

    public function getFieldName(): string
    {
        return 'createdAt';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getCreatedAt();
    }
}
