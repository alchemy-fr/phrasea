<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle;

use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Admin\Field\ObjectTypeChoiceField;
use Alchemy\TrackBundle\Controller\ChangeLogCrudController;
use Alchemy\TrackBundle\Doctrine\LoggableChangeSetListener;
use Alchemy\TrackBundle\Service\ChangeLogManager;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class AlchemyTrackBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('entities_map')
                    ->useAttributeAsKey('key')
                        ->prototype('scalar')
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $objectMappingServiceId = 'alchemy_track.object_mapping';

        if (array_any(array_keys($config['entities_map']), fn ($v) => !is_numeric($v))) {
            throw new \InvalidArgumentException('The "entities_map" configuration keys must be positive integers.');
        }

        $entityMap = [];
        foreach ($config['entities_map'] as $key => $className) {
            if (!class_exists($className)) {
                throw new \InvalidArgumentException(sprintf('The class "%s" defined in the "entities_map" configuration does not exist.', $className));
            }
            $entityMap[(int) $key] = $className;
        }

        $services->set($objectMappingServiceId)
            ->class(ObjectMapping::class)
            ->arg('$map', $config['entities_map']);

        $services->set(ChangeLogManager::class)
            ->arg('$objectMapping', service($objectMappingServiceId));
        $services->set(LoggableChangeSetListener::class);
        $services->set(ChangeLogCrudController::class)
            ->arg('$objectMapping', service($objectMappingServiceId));
        $services->set(ObjectTypeChoiceField::class)
            ->arg('$objectMapping', service($objectMappingServiceId));
    }
}
