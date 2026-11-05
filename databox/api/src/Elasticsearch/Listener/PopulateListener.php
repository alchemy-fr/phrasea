<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Elasticsearch\AssetPermissionComputer;
use App\Service\Asset\Attribute\AttributeValueResolver;
use Elastic\Elasticsearch\Traits\EndpointTrait;
use Elastica\Index\Settings;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

readonly class PopulateListener implements EventSubscriberInterface
{
    use EndpointTrait;

    public function __construct(
        private IndexManager $indexManager,
        private CacheInterface $fosPopulateCache,
        private AssetPermissionComputer $assetPermissionComputer,
        private TemporaryCacheFactory $temporaryCacheFactory,
        private AttributeValueResolver $attributeValueResolver,
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

        $this->assetPermissionComputer->setWorkspaceCache(new ArrayAdapter(deepClone: false));
        $this->assetPermissionComputer->setCollectionCache($this->fosPopulateCache);
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        $this->assetPermissionComputer->disableCollectionCache();
        $this->assetPermissionComputer->disableWorkspaceCache();
        $this->assetPermissionComputer->disableAssetCache();
        $this->attributeValueResolver->reset();

        $index = $this->indexManager->getIndex($event->getIndex());

        $request = $this->createRequest(
            'POST',
            '_forcemerge?max_num_segments=5',
            [],
        );
        $index->getClient()->sendRequest($request);
        $index->getSettings()->setRefreshInterval(Settings::DEFAULT_REFRESH_INTERVAL);
    }

    /**
     * Runs after the listener of FOS Elastica that clears the entity manager (priority 0):
     * the Doctrine graph of the page is full of cycles, which only the cycle collector frees.
     * Left to its adaptive threshold, it lets the cleared pages pile up.
     */
    public function afterPageCleared(PostInsertObjectsEvent $event): void
    {
        $this->temporaryCacheFactory->reset();
        $this->attributeValueResolver->reset();
        gc_collect_cycles();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
            PostInsertObjectsEvent::class => ['afterPageCleared', -1024],
        ];
    }
}
