<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Entity\Core\Asset;

class EditedAtBuiltInAttribute extends AbstractDateTimeBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'edited_at';
    }

    public static function getKey(): string
    {
        return '@editedAt';
    }

    public static function getName(): string
    {
        return 'editedAt';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getEditedAt();
    }
}
