<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::KEY)]
class EditedAtBuiltInAttribute extends AbstractDateTimeBuiltInAttribute
{
    public const string KEY = '@editedAt';

    protected function getAggregationTranslationKey(): string
    {
        return 'edited_at';
    }

    public static function getKey(): string
    {
        return self::KEY;
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
