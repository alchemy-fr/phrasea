<?php

namespace Alchemy\SecurityTokenBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AlchemySecurityTokenExtension extends Extension
{
    private ?array $signerFactories = null;

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('factories.yaml');

        return new Configuration($this->getSignerFactories($container));
    }

    private function getSignerFactories(ContainerBuilder $container): array
    {
        if (null !== $this->signerFactories) {
            return $this->signerFactories;
        }

        $factories = array();
        $services = $container->findTaggedServiceIds('alchemy_security_token.signer_factory');

        foreach (array_keys($services) as $id) {
            $factory = $container->get($id);
            $factories[str_replace('-', '_', $factory->getKey())] = $factory;
        }

        return $this->signerFactories = $factories;
    }
}
