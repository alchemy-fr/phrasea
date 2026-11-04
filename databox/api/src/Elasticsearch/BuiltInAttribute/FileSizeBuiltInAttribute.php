<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\NumberAttributeType;
use App\Entity\Core\Asset;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::KEY)]
final class FileSizeBuiltInAttribute extends AbstractBuiltInAttribute
{
    public const string KEY = '@size';

    protected function getAggregationTranslationKey(): string
    {
        return 'file_size';
    }

    public static function getName(): string
    {
        return 'fileSize';
    }

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceFileSize();
    }

    #[\Override]
    public function getType(): string
    {
        return NumberAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
