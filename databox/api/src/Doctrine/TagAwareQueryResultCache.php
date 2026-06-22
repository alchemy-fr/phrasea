<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Annotation\IgnoreAutowire;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @IgnoreAutowire
 */
class TagAwareQueryResultCache implements CacheItemPoolInterface
{
    public function __construct(private readonly TagAwareCacheInterface $cache, private readonly array $tags)
    {
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->cache->save($item);
    }

    public function getItem(string $key): CacheItemInterface
    {
        $item = $this->cache->getItem($key);

        if ($item instanceof ItemInterface) {
            $item->tag($this->tags);
        }

        return $item;
    }

    public function getItems(array $keys = []): \Traversable|array
    {
        return $this->cache->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->cache->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function deleteItem(mixed $key): bool
    {
        return $this->cache->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->cache->deleteItems($keys);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->cache->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->cache->commit();
    }
}
