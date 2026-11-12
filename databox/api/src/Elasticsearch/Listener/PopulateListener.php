<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Elasticsearch\AssetIndexPreloader;
use App\Elasticsearch\AssetPermissionComputer;
use App\Service\Asset\Attribute\AttributeValueResolver;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Persister\Event\OnExceptionEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreInsertObjectsEvent;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

readonly class PopulateListener implements EventSubscriberInterface
{
    public function __construct(
        private CacheInterface $fosPopulateCache,
        private AssetPermissionComputer $assetPermissionComputer,
        private TemporaryCacheFactory $temporaryCacheFactory,
        private AttributeValueResolver $attributeValueResolver,
        private AssetIndexPreloader $assetIndexPreloader,
    ) {
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
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
        $this->assetIndexPreloader->reset();
    }

    /**
     * The preloaded values of a failed page must not be served to the indexing that comes next.
     */
    public function onException(OnExceptionEvent $event): void
    {
        $this->assetIndexPreloader->reset();
    }

    /**
     * Runs after the listeners that may filter the objects of the page.
     */
    public function preloadPage(PreInsertObjectsEvent $event): void
    {
        $this->assetIndexPreloader->preload($event->getObjects());
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
        $this->assetIndexPreloader->reset();
        gc_collect_cycles();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreIndexPopulateEvent::class => 'preIndexPopulate',
            PostIndexPopulateEvent::class => 'postIndexPopulate',
            PreInsertObjectsEvent::class => ['preloadPage', -1024],
            PostInsertObjectsEvent::class => ['afterPageCleared', -1024],
            OnExceptionEvent::class => 'onException',
        ];
    }
}
