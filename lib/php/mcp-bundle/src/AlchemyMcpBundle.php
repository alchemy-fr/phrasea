<?php

declare(strict_types=1);

namespace Alchemy\McpBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AlchemyMcpBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('server')
                    ->info('Key of the server in the "mcp.servers" configuration')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('resource_name')
                    ->info('Human-readable name of the MCP server, advertised in the OAuth protected resource metadata')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('api_name')
                    ->info('Name of the API in the errors reported to the MCP client (defaults to resource_name)')
                    ->defaultNull()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $server = null;
        foreach ($builder->getExtensionConfig('alchemy_mcp') as $config) {
            $server = $config['server'] ?? $server;
        }
        if (!is_string($server) || '' === $server) {
            return;
        }

        $builder->prependExtensionConfig('mcp', [
            'servers' => [
                $server => [
                    'http' => [
                        'path' => '/mcp',
                        // Requests are authenticated by a Bearer token (see security.yaml), which a browser never
                        // attaches to a rebound request: the DNS rebinding protection would only reject the public host.
                        'allowed_hosts' => false,
                    ],
                    'session' => [
                        // Shared across PHP workers/replicas, unlike the default file store.
                        'store' => 'framework',
                    ],
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('alchemy_mcp.server', $config['server'])
            ->set('alchemy_mcp.resource_name', $config['resource_name'])
            ->set('alchemy_mcp.api_name', $config['api_name'] ?? $config['resource_name']);

        $container->import('../config/services.yaml');
    }
}
