<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostIndexMappingBuildEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class PostIndexMappingListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private IndexMappingUpdater $indexMappingUpdater,
    ) {
    }

    public function configureIndex(PostIndexMappingBuildEvent $event): void
    {
        if ('asset' !== $event->getIndex()) {
            return;
        }

        $mapping = $event->getMapping();

        $workspaces = $this->em->getRepository(Workspace::class)->findAll();
        foreach ($workspaces as $workspace) {
            /** @var AttributeDefinition[] $attributeDefinitions */
            $attributeDefinitions = $this->em->getRepository(AttributeDefinition::class)
                ->findBy([
                    'workspace' => $workspace->getId(),
                ]);

            foreach ($attributeDefinitions as $definition) {
                $this->indexMappingUpdater->assignAttributeDefinitionToMapping($mapping, $definition);
            }
        }

        $event->setMapping($mapping);
    }

    public static function getSubscribedEvents()
    {
        return [
            PostIndexMappingBuildEvent::class => 'configureIndex',
        ];
    }
}
