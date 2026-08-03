<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Entity\Core\Asset;

class CreatedAtBuiltInAttribute extends AbstractDateTimeBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'created_at';
    }

    public static function getKey(): string
    {
        return '@createdAt';
    }

    public static function getName(): string
    {
        return 'createdAt';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getCreatedAt();
    }
}
