<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\AssetPermissionComputer;
use Elastica\Index\Settings;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

readonly class PopulateListener implements EventSubscriberInterface
{
    public function __construct(
        private IndexManager $indexManager,
        private CacheInterface $fosPopulateCache,
        private AssetPermissionComputer $assetPermissionComputer,
    ) {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();
        if ($settings->getIndex()->exists()) {
            $settings->setRefreshInterval('-1');
        }

        if ($this->fosPopulateCache instanceof CacheItemPoolInterface) {
            $this->fosPopulateCache->clear();
        }

        $this->assetPermissionComputer->setCollectionCache($this->fosPopulateCache);
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        $this->assetPermissionComputer->disableCollectionCache();

        $index = $this->indexManager->getIndex($event->getIndex());
        $index->getClient()->request('_forcemerge?max_num_segments=5', 'POST');
        $index->getSettings()->setRefreshInterval(Settings::DEFAULT_REFRESH_INTERVAL);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
        ];
    }
}
