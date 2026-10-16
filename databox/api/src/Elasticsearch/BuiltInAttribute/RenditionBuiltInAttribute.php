<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::KEY)]
final class RenditionBuiltInAttribute extends AbstractEntityBuiltInAttribute
{
    public const string KEY = '@rendition';

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
        return self::KEY;
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
