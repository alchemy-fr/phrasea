<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\DependencyInjection\Compiler;

use Alchemy\ESBundle\Indexer\IndexPersister;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SearchIndexPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(IndexPersister::class)) {
            return;
        }

        $service = $container->getDefinition(IndexPersister::class);
        $taggedServices = $container->findTaggedServiceIds('fos_elastica.persister');

        $persisters = [];
        /** @var string|ObjectPersisterInterface $id */
        foreach ($taggedServices as $id => $tags) {
            $def = $container->getDefinition($id);
            if ($def->isAbstract()) {
                continue;
            }

            $class = $def->getArgument('index_2');
            $persisters[$class] ??= [];
            $persisters[$class][] = new Reference($id);
        }

        $service->setArgument('$persisters', $persisters);
    }
}
