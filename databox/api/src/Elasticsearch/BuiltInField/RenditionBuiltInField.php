<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;

final class RenditionBuiltInField extends AbstractEntityBuiltInField
{
    protected function getEntityClass(): string
    {
        return RenditionDefinition::class;
    }

    /**
     * @param RenditionDefinition $value
     */
    #[\Override]
    public function resolveLabel($value): string
    {
        return $value->getName();
    }

    public static function getName(): string
    {
        return 'renditions';
    }

    public static function getKey(): string
    {
        return '@rendition';
    }

    public function getValueFromAsset(Asset $asset): never
    {
        throw new \LogicException('Should never be called');
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'renditions';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    #[\Override]
    public function isMultiple(): bool
    {
        return true;
    }
}
