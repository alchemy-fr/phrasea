<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\BooleanAttributeType;
use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::KEY)]
final class IsStoryBuiltInAttribute extends AbstractBuiltInAttribute
{
    public const string KEY = '@isStory';

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
        return self::KEY;
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
