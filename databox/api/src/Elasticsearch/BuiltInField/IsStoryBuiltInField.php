<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\BooleanAttributeType;
use App\Entity\Core\Asset;

final class IsStoryBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'is_story';
    }

    public static function getName(): string
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

    #[\Override]
    public function getType(): string
    {
        return BooleanAttributeType::NAME;
    }
}
