<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Elastica\Index\Settings;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

class PopulateListener implements EventSubscriberInterface
{
    public function __construct(private readonly IndexManager $indexManager, private readonly CacheInterface $cache, private readonly AssetPostTransformListener $assetPostTransformListener)
    {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();
        if ($settings->getIndex()->exists()) {
            $settings->setRefreshInterval('-1');
        }

        if ($this->cache instanceof CacheItemPoolInterface) {
            $this->cache->clear();
        }

        $this->assetPostTransformListener->setCache($this->cache);
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event)
    {
        $this->assetPostTransformListener->disableCache();

        $index = $this->indexManager->getIndex($event->getIndex());
        $index->getClient()->request('_forcemerge?max_num_segments=5', 'POST');
        $index->getSettings()->setRefreshInterval(Settings::DEFAULT_REFRESH_INTERVAL);
    }

    public static function getSubscribedEvents()
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
        ];
    }
}
