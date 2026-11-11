<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Tests;

use Alchemy\McpBundle\AlchemyMcpBundle;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class AlchemyMcpBundleTest extends TestCase
{
    public function testServerDefaultsAreMergedWithTheApplicationConfig(): void
    {
        $builder = new ContainerBuilder();
        $mcpExtension = (new McpBundle())->getContainerExtension();
        $alchemyExtension = (new AlchemyMcpBundle())->getContainerExtension();
        $builder->registerExtension($mcpExtension);
        $builder->registerExtension($alchemyExtension);

        $builder->loadFromExtension('alchemy_mcp', ['server' => 'expose', 'resource_name' => 'Phrasea Expose']);
        $builder->loadFromExtension('mcp', ['servers' => ['expose' => [
            'name' => 'Phrasea Expose',
            'session' => ['ttl' => 600],
            'registry' => ['tools' => ['App\Mcp\Tool\\']],
        ]]]);

        $this->assertInstanceOf(PrependExtensionInterface::class, $alchemyExtension);
        $alchemyExtension->prepend($builder);

        $this->assertInstanceOf(ConfigurationExtensionInterface::class, $mcpExtension);
        $configuration = $mcpExtension->getConfiguration([], $builder);
        $this->assertInstanceOf(ConfigurationInterface::class, $configuration);
        $config = (new Processor())->processConfiguration($configuration, $builder->getExtensionConfig('mcp'));

        $this->assertSame(['expose'], array_keys($config['servers']));
        $server = $config['servers']['expose'];
        $this->assertSame('Phrasea Expose', $server['name']);
        $this->assertSame('/mcp', $server['http']['path']);
        $this->assertFalse($server['http']['allowed_hosts']);
        $this->assertSame('framework', $server['session']['store']);
        $this->assertSame(600, $server['session']['ttl']);
    }

    public function testNothingIsPrependedWithoutServer(): void
    {
        $builder = new ContainerBuilder();
        $alchemyExtension = (new AlchemyMcpBundle())->getContainerExtension();
        $builder->registerExtension($alchemyExtension);

        $this->assertInstanceOf(PrependExtensionInterface::class, $alchemyExtension);
        $alchemyExtension->prepend($builder);

        $this->assertSame([], $builder->getExtensionConfig('mcp'));
    }
}
