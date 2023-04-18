<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Repository\Cache\AttributeDefinitionRepositoryMemoryCachedDecorator;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Events;

class MemoryCacheInvalidatorListener implements EventSubscriber
{
    private AttributeDefinitionRepositoryMemoryCachedDecorator $cache;

    public function __construct(AttributeDefinitionRepositoryMemoryCachedDecorator $cache)
    {
        $this->cache = $cache;
    }

    public function onClear(OnClearEventArgs $args): void
    {
        $this->cache->invalidateList();
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onClear,
        ];
    }
}
