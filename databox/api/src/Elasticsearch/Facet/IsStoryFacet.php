<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Attribute\Type\BooleanAttributeType;
use App\Entity\Core\Asset;

final class IsStoryFacet extends AbstractFacet
{
    protected function getAggregationTranslationKey(): string
    {
        return 'is_story';
    }

    public function getFieldName(): string
    {
        return 'isStory';
    }

    public static function getKey(): string
    {
        return '@isStory';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->isStory();
    }

    public function getType(): string
    {
        return BooleanAttributeType::getName();
    }
}
