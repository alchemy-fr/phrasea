<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Config;

use App\Integration\Config\ConfigSchemaDumper;
use App\Integration\Config\ConfigWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigSchemaDumperTest extends TestCase
{
    private array $properties;

    protected function setUp(): void
    {
        $treeBuilder = new TreeBuilder('root');
        $children = $treeBuilder->getRootNode()->children();

        ConfigWidget::apply($children->scalarNode('attribute'), ConfigWidget::Attribute, ['type' => 'entity', 'multiple' => true])
                ->isRequired()
                ->cannotBeEmpty()
                ->info('Slug of the attribute')
            ->end()
            ->scalarNode('unknownLabel')
                ->defaultValue('Unknown')
                ->example('Nobody')
            ->end()
            ->scalarNode('apiKey')
                ->attribute(ConfigWidget::ATTR_SECRET, true)
            ->end()
            ->integerNode('maxImageSize')
                ->defaultValue(2048)
                ->min(256)
                ->max(4096)
            ->end()
            ->floatNode('minConfidence')
                ->min(0)
                ->max(1)
            ->end()
            ->booleanNode('processIncoming')
                ->defaultFalse()
            ->end()
            ->enumNode('method')
                ->values(['api', 'enqueue'])
                ->defaultValue('api')
            ->end()
            ->arrayNode('position')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('top')->defaultValue('50%')->end()
                ->end()
            ->end()
            ->arrayNode('labels')
                ->canBeEnabled()
                ->children()
                    ->scalarNode('rendition')
                        ->attribute(ConfigWidget::ATTR_WIDGET, ConfigWidget::Rendition->value)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('emails')
                ->requiresAtLeastOneElement()
                ->scalarPrototype()
                    ->attribute(ConfigWidget::ATTR_WIDGET, ConfigWidget::Email->value)
                ->end()
            ->end()
            ->arrayNode('actions_on_reject')
                ->enumPrototype()
                    ->values(['reject', 'quarantine'])
                ->end()
            ->end()
            ->arrayNode('attributes')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('name')->isRequired()->end()
                        ->floatNode('threshold')->end()
                    ->end()
                ->end()
            ->end()
            ->variableNode('extra')
            ->end()
            ->arrayNode('options')
            ->end()
        ;

        $this->properties = (new ConfigSchemaDumper())->dump($treeBuilder->buildTree())['properties'];
    }

    public function testDumpsAllTheChildrenOfTheRoot(): void
    {
        $this->assertSame([
            'attribute',
            'unknownLabel',
            'apiKey',
            'maxImageSize',
            'minConfidence',
            'processIncoming',
            'method',
            'position',
            'labels',
            'emails',
            'actions_on_reject',
            'attributes',
            'extra',
            'options',
        ], array_keys($this->properties));
    }

    public function testRequiredScalarWithWidget(): void
    {
        $this->assertSame([
            'type' => 'string',
            'label' => 'Attribute',
            'info' => 'Slug of the attribute',
            'required' => true,
            'widget' => 'attribute',
            'widgetOptions' => ['type' => 'entity', 'multiple' => true],
        ], $this->properties['attribute']);
    }

    public function testScalarWithDefaultAndExample(): void
    {
        $this->assertSame([
            'type' => 'string',
            'label' => 'Unknown label',
            'example' => 'Nobody',
            'default' => 'Unknown',
        ], $this->properties['unknownLabel']);
    }

    public function testSecret(): void
    {
        $this->assertSame([
            'type' => 'string',
            'label' => 'Api key',
            'secret' => true,
        ], $this->properties['apiKey']);
    }

    public function testNumbers(): void
    {
        $this->assertSame([
            'type' => 'integer',
            'min' => 256,
            'max' => 4096,
            'label' => 'Max image size',
            'default' => 2048,
        ], $this->properties['maxImageSize']);

        $this->assertSame([
            'type' => 'float',
            'min' => 0,
            'max' => 1,
            'label' => 'Min confidence',
        ], $this->properties['minConfidence']);
    }

    public function testBoolean(): void
    {
        $this->assertSame([
            'type' => 'boolean',
            'label' => 'Process incoming',
            'default' => false,
        ], $this->properties['processIncoming']);
    }

    public function testEnum(): void
    {
        $this->assertSame([
            'type' => 'enum',
            'values' => ['api', 'enqueue'],
            'label' => 'Method',
            'default' => 'api',
        ], $this->properties['method']);
    }

    public function testNestedObject(): void
    {
        $position = $this->properties['position'];
        $this->assertSame('object', $position['type']);
        $this->assertArrayNotHasKey('toggle', $position);
        $this->assertSame(['type' => 'string', 'label' => 'Top', 'default' => '50%'], $position['properties']['top']);
        $this->assertSame(['top' => '50%'], $position['default']);
    }

    public function testObjectThatCanBeEnabled(): void
    {
        $labels = $this->properties['labels'];
        $this->assertSame('object', $labels['type']);
        $this->assertSame('enabled', $labels['toggle']);
        $this->assertSame(['type' => 'boolean', 'label' => 'Enabled', 'default' => false], $labels['properties']['enabled']);
        $this->assertSame('rendition', $labels['properties']['rendition']['widget']);
    }

    public function testListOfScalars(): void
    {
        $emails = $this->properties['emails'];
        $this->assertSame('list', $emails['type']);
        $this->assertSame(1, $emails['minItems']);
        $this->assertSame('Emails', $emails['label']);
        $this->assertSame([], $emails['default']);
        $this->assertSame(['type' => 'string', 'widget' => 'email'], $emails['items']);
    }

    public function testListOfEnums(): void
    {
        $actions = $this->properties['actions_on_reject'];
        $this->assertSame('list', $actions['type']);
        $this->assertSame('Actions on reject', $actions['label']);
        $this->assertArrayNotHasKey('minItems', $actions);
        $this->assertSame(['type' => 'enum', 'values' => ['reject', 'quarantine']], $actions['items']);
    }

    public function testListOfObjects(): void
    {
        $items = $this->properties['attributes']['items'];
        $this->assertSame('object', $items['type']);
        $this->assertSame(['type' => 'string', 'label' => 'Name', 'required' => true], $items['properties']['name']);
        $this->assertSame(['type' => 'float', 'label' => 'Threshold'], $items['properties']['threshold']);
    }

    public function testFreeNodes(): void
    {
        $this->assertSame(['type' => 'any', 'label' => 'Extra'], $this->properties['extra']);
        $this->assertSame('any', $this->properties['options']['type']);
    }

    public function testAnEmptyRootIsAnObject(): void
    {
        $treeBuilder = new TreeBuilder('root');
        $treeBuilder->getRootNode()->children();

        $this->assertSame(['type' => 'object', 'properties' => []], (new ConfigSchemaDumper())->dump($treeBuilder->buildTree()));
    }

    #[DataProvider('getKeys')]
    public function testHumanize(string $key, string $expected): void
    {
        $this->assertSame($expected, ConfigSchemaDumper::humanize($key));
    }

    public static function getKeys(): iterable
    {
        yield ['maxImageSize', 'Max image size'];
        yield ['actions_on_reject', 'Actions on reject'];
        yield ['attribute', 'Attribute'];
        yield ['', ''];
    }
}
