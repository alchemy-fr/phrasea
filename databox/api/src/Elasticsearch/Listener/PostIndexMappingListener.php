<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\Mapping\IndexMappingTemplatesMaker;
use FOS\ElasticaBundle\Event\PostIndexMappingBuildEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class PostIndexMappingListener implements EventSubscriberInterface
{
    public function __construct(
        private IndexMappingTemplatesMaker $indexMappingUpdater,
    ) {
    }

    public function configureIndex(PostIndexMappingBuildEvent $event): void
    {
        if ('asset' !== $event->getIndex()) {
            return;
        }

        $mapping = $event->getMapping();
        $mapping['dynamic_templates'] = $this->indexMappingUpdater->getAssetDynamicTemplates();

        $event->setMapping($mapping);
    }

    public static function getSubscribedEvents()
    {
        return [
            PostIndexMappingBuildEvent::class => 'configureIndex',
        ];
    }
}
