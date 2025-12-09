<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Api\Traits\UserLocaleTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\CollectionAsset;
use App\Service\Asset\Attribute\AssetTitleResolver;
use Doctrine\ORM\EntityManagerInterface;

final class StoryBuiltInField extends AbstractEntityBuiltInField
{
    use UserLocaleTrait;

    public function __construct(
        private readonly AssetTitleResolver $assetTitleResolver,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($em);
    }

    /**
     * @param Asset $value
     */
    public function resolveItem($value): array
    {
        return [
            'title' => $this->resolveLabel($value),
        ];
    }

    /**
     * @param Asset $value
     */
    protected function resolveLabel($value): string
    {
        $attribute = $this->assetTitleResolver->resolveTitleWithoutIndex($value, $this->getPreferredLocales($value->getWorkspace()));
        if ($attribute instanceof Attribute) {
            return (string) $attribute->getValue();
        }

        return $attribute ?? '';
    }

    protected function getEntityClass(): string
    {
        return Asset::class;
    }

    public function getFieldName(): string
    {
        return 'stories';
    }

    public static function getKey(): string
    {
        return '@story';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getCollections()->filter(function (CollectionAsset $collectionAsset): bool {
            return $collectionAsset->getCollection()->isStory();
        })->map(function (CollectionAsset $collectionAsset) {
            return $collectionAsset->getCollection()->getStoryAsset();
        });
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'stories';
    }

    public function isFacet(): bool
    {
        return false;
    }
}
