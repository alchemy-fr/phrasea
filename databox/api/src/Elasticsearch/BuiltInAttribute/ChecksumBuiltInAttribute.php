<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\KeywordAttributeType;
use App\Entity\Core\Asset;

final class ChecksumBuiltInAttribute extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'checksum';
    }

    public static function getName(): string
    {
        return 'checksum';
    }

    public static function getKey(): string
    {
        return '@checksum';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getSourceChecksum();
    }

    #[\Override]
    public function getType(): string
    {
        return KeywordAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
