<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::KEY)]
final class FileExtensionBuiltInAttribute extends AbstractBuiltInAttribute
{
    public const string KEY = '@extension';

    protected function getAggregationTranslationKey(): string
    {
        return 'file_extension';
    }

    public static function getName(): string
    {
        return 'fileExtension';
    }

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileExtension();
    }

    #[\Override]
    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return true;
    }
}
