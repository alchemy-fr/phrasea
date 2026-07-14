<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\IdAttributeType;
use App\Entity\Core\Asset;

final class IdBuiltInField extends AbstractBuiltInAttribute
{
    protected function getAggregationTranslationKey(): string
    {
        return 'id';
    }

    public static function getName(): string
    {
        return '_id';
    }

    public static function getKey(): string
    {
        return '@id';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getId();
    }

    #[\Override]
    public function getType(): string
    {
        return IdAttributeType::getName();
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
