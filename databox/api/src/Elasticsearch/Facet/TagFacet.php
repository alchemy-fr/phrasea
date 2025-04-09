<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\Type\TagAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Tag;

final class TagFacet extends AbstractEntityFacet
{
    use UserLocaleTrait;

    /**
     * @param Tag $value
     */
    public function resolveItem($value): array
    {
        return [
            'name' => $this->resolveLabel($value),
            'nameTranslated' => $this->resolveTranslatedLabel($value),
            'color' => $value->getColor(),
        ];
    }

    /**
     * @param Tag $value
     */
    protected function resolveLabel($value): string
    {
        return $value->getName();
    }

    protected function resolveTranslatedLabel(Tag $value): string
    {
        $preferredLocales = $this->getPreferredLocales($value->getWorkspace());

        $translations = $value->getTranslations()['name'] ?? [];
        $key = LocaleUtil::getBestLocale(array_keys($translations), $preferredLocales);
        if (null !== $key) {
            return $translations[$key];
        }

        return $value->getName();
    }

    protected function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getFieldName(): string
    {
        return 'tags';
    }

    public static function getKey(): string
    {
        return '@tag';
    }

    public function getType(): string
    {
        return TagAttributeType::NAME;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getTags();
    }

    protected function getAggregationTitle(): string
    {
        return 'Tags';
    }
}
