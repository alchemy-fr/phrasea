<?php

namespace Alchemy\CoreBundle\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Cache\CacheInterface;

#[AsEventListener(KernelEvents::TERMINATE, method: 'reset', priority: -5)]
#[AsEventListener(ConsoleEvents::TERMINATE, method: 'reset', priority: -5)]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'reset', priority: -5)]
class TemporaryCacheFactory implements ResettableInterface
{
    /**
     * @var ArrayAdapter[]
     */
    private array $caches = [];

    public function createCache(): CacheInterface
    {
        $arrayAdapter = new ArrayAdapter();
        $this->caches[] = $arrayAdapter;

        return $arrayAdapter;
    }

    public function reset(): void
    {
        foreach ($this->caches as $cache) {
            $cache->reset();
        }
    }
}
