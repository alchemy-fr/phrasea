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

    public function save(CacheItemInterface $item)
    {
        return $this->cache->save($item);
    }

    public function getItem($key)
    {
        $item = $this->cache->getItem($key);

        if ($item instanceof ItemInterface) {
            $item->tag($this->tags);
        }

        return $item;
    }

    public function getItems(array $keys = [])
    {
        return $this->cache->getItems($keys);
    }

    public function hasItem($key)
    {
        return $this->cache->hasItem($key);
    }

    public function clear()
    {
        return $this->cache->clear();
    }

    public function deleteItem($key)
    {
        return $this->cache->deleteItem($key);
    }

    public function deleteItems(array $keys)
    {
        return $this->cache->deleteItems($keys);
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        return $this->cache->saveDeferred($item);
    }

    public function commit()
    {
        return $this->cache->commit();
    }
}
