<?php

declare(strict_types=1);

namespace App\Repository\Cache;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Repository\Core\AttributeRepositoryInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AttributeRepositoryMemoryCachedDecorator implements AttributeRepositoryInterface, CacheRepositoryInterface
{
    use CacheDecoratorTrait;

    private TagAwareCacheInterface $cache;

    public function __construct(AttributeRepositoryInterface $decorated, TagAwareCacheInterface $memoryCache)
    {
        $this->decorated = $decorated;
        $this->cache = $memoryCache;
    }

    public function getDuplicates(Attribute $attribute): array
    {
        return $this->decorated->getDuplicates($attribute);
    }

    public function getAssetAttributes(Asset $asset): array
    {
        return $this->cache->get(
            'attr_'.$asset->getId(),
            function (ItemInterface $item) use ($asset) {
                $item->tag(AttributeRepositoryInterface::LIST_TAG);

                return $this->decorated->getAssetAttributes($asset);
            });
    }

    public function invalidateEntity(string $id): void
    {
    }

    public function invalidateList(): void
    {
        $this->cache->invalidateTags([self::LIST_TAG]);

        if ($this->decorated instanceof CacheRepositoryInterface) {
            $this->decorated->invalidateList();
        }
    }
}