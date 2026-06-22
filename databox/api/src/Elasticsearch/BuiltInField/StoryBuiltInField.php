<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use App\Service\Asset\Attribute\AssetNameResolver;
use Doctrine\ORM\EntityManagerInterface;

final class StoryBuiltInField extends AbstractEntityBuiltInField
{
    public function __construct(
        private readonly AssetNameResolver $assetNameResolver,
        EntityManagerInterface $em,
    ) {
        parent::__construct($em);
    }

    /**
     * @param Asset $value
     */
    public function resolveItem($value): array
    {
        return [
            'name' => $this->resolveLabel($value),
        ];
    }

    /**
     * @param Asset $value
     */
    #[\Override]
    protected function resolveLabel($value): string
    {
        return $this->assetNameResolver->resolveNameAsString($value) ?? '';
    }

    protected function getEntityClass(): string
    {
        return Asset::class;
    }

    public static function getName(): string
    {
        return 'stories';
    }

    public static function getKey(): string
    {
        return '@story';
    }

    #[\Override]
    public function isMultiple(): bool
    {
        return true;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getCollections()->filter(fn (CollectionAsset $collectionAsset): bool => $collectionAsset->getCollection()->isStory())->map(fn (CollectionAsset $collectionAsset) => $collectionAsset->getCollection()->getStoryAsset());
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'stories';
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
