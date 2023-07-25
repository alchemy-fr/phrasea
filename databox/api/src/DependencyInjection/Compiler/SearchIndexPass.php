<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Elasticsearch\ESSearchIndexer;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SearchIndexPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ESSearchIndexer::class)) {
            return;
        }

        $service = $container->getDefinition(ESSearchIndexer::class);
        $taggedServices = $container->findTaggedServiceIds('fos_elastica.persister');

        /** @var string|ObjectPersisterInterface $id */
        foreach ($taggedServices as $id => $tags) {
            $def = $container->getDefinition($id);
            if ($def->isAbstract()) {
                continue;
            }
            $service->addMethodCall('addObjectPersister', [$def->getArgument('index_2'), new Reference($id)]);
        }
    }
}
