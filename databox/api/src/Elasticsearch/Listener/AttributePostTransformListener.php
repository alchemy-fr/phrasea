<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\AssetPermissionComputer;
use App\Entity\Core\Attribute;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

final  class AttributePostTransformListener implements EventSubscriberInterface
{
    private CacheInterface $cache;

    public function __construct(
        private readonly AssetPermissionComputer $assetPermissionComputer,
    ) {
        $this->disableCache();
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function disableCache(): void
    {
        $this->cache = new NullAdapter();
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Attribute $attribute */
        if (!($attribute = $event->getObject()) instanceof Attribute) {
            return;
        }

        $document = $event->getDocument();

        $asset = $attribute->getAsset();
        $permFields = $this->cache->get('pf_'.$asset->getId(), function () use ($asset): array {
            return $this->assetPermissionComputer->getAssetPermissionFields($asset);
        });
        foreach ($permFields as $key => $value) {
            $document->set($key, $value);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
