<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Tag;

final class TagBuiltInField extends AbstractEntityBuiltInField
{
    use UserLocaleTrait;

    /**
     * @param Tag $value
     */
    public function resolveItem($value): array
    {
        return [
            'name' => $this->resolveLabel($value),
            'displayName' => $this->resolveDisplayName($value),
            'color' => $value->getColor(),
        ];
    }

    /**
     * @param Tag $value
     */
    protected function resolveLabel($value): string
    {
        return $this->resolveDisplayName($value);
    }

    protected function resolveDisplayName(Tag $value): string
    {
        $preferredLocales = $this->getPreferredLocales($value->getWorkspace());

        return $value->getTranslatedField(Tag::TR_FIELD_NAME, $preferredLocales, $value->getName());
    }

    protected function getEntityClass(): string
    {
        return Tag::class;
    }

    public static function getName(): string
    {
        return 'tags';
    }

    public static function getKey(): string
    {
        return '@tag';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getTags();
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'tags';
    }

    public function isMultiple(): bool
    {
        return true;
    }
}
