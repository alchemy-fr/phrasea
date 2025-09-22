<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Entity\Core\Asset;

class EditedAtBuiltInField extends AbstractDateTimeBuiltInField
{
    protected function getAggregationTranslationKey(): string
    {
        return 'edited_at';
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
