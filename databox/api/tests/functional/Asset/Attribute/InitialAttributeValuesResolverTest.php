<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset\Attribute;

use App\Attribute\AttributeAssigner;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Notification\ExceptionNotifier;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\AttributeValueResolver;
use App\Service\Asset\Attribute\InitialAttributeValuesResolver;
use App\Service\Asset\Attribute\TemplateResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Nonstandard\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

class InitialAttributeValuesResolverTest extends KernelTestCase
{
    private AttributeAssigner $attributeAssigner;

    public function setUp(): void
    {
        self::bootKernel();
        $this->attributeAssigner = static::getContainer()->get(AttributeAssigner::class);
    }

    public static function dataProvider(): array
    {
        return array_map(
            fn ($test) => [
                $test['definitions'],
                $test['metadata'],
                $test['expected'],
            ],
            array_filter(
                // the data file is used for documentation generation AND as a data provider for tests
                Yaml::parseFile(__DIR__.'/../../../../src/Documentation/InitialAttributeValuesResolverData.yaml'),
                fn ($test) => $test['test'] ?? true
            )
        );
    }

    /**
     * @param array<string, string|string[]> $metadata
     */
    #[DataProvider('dataProvider')]
    public function testResolveInitialAttributes(array $definitions, ?array $metadata, array $expected): void
    {
        $attributeDefinitions = [];

        $workspace = $this->createMock(Workspace::class);
        $workspace->method('getId')
            ->willReturn(Uuid::uuid4()->toString());

        foreach ($definitions as $name => $definition) {
            if (null !== ($initialValues = $definition['initialValues'] ?? null)) {
                $initialValues = is_array($initialValues) ? $initialValues : ['_' => $initialValues];
            }
            $ad = $this->createMock(AttributeDefinition::class);
            $ad->method('getName')
                ->willReturn($name);
            $ad->method('isEnabled')
                ->willReturn(true);
            $ad->method('getWorkspace')
                ->willReturn($workspace);
            $ad->method('isMultiple')
                ->willReturn($definition['isMultiple'] ?? false);
            $ad->method('isTranslatable')
                ->willReturn($definition['isTranslatable'] ?? false);
            $ad->method('getInitialValues')
                ->willReturn($initialValues);
            $ad->method('getReadFromMetadata')
                ->willReturn($definition['readFromMetadata'] ?? null);
            $ad->method('getType')
                ->willReturn($definition['type'] ?? TextAttributeType::NAME);
            $attributeDefinitions[] = $ad;
        }

        /** @var AttributeDefinitionRepository $adr */
        $adr = $this->createMock(AttributeDefinitionRepository::class);
        $adr
            ->method('getWorkspaceInitializeDefinitions')
            ->willReturn($attributeDefinitions);

        $fileMock = $this->createMock(File::class);
        $fileMock
            ->method('getMetadata')
            ->willReturnCallback(function (?string $name) use ($metadata) {
                if (null === $name) {
                    return $metadata;
                }

                return $metadata[$name] ?? null;
            });
        $fileMock
            ->method('getMetadataNameValues')
            ->willReturnCallback(function (string $name) use ($metadata) {
                return $metadata[$name] ?? null;
            });

        $assetMock = $this->createMock(Asset::class);
        $assetMock
            ->method('getSource')
            ->willReturn($fileMock);
        $assetMock
            ->method('getId')
            ->willReturn(Uuid::uuid4()->toString());

        $templateResolver = new TemplateResolver();

        $attributeTypeRegistry = $this->createMock(AttributeTypeRegistry::class);
        $textAttributeType = new TextAttributeType();

        $attributeTypeRegistry
            ->method('getType')
            ->willReturn($textAttributeType);

        $attributeValueResolver = new AttributeValueResolver(
            $templateResolver,
            $attributeTypeRegistry,
            $adr,
        );

        $iavr = new InitialAttributeValuesResolver(
            $attributeValueResolver,
            $adr,
            $this->attributeAssigner,
            static::getContainer()->get(ExceptionNotifier::class),
        );

        $result = [];
        /** @var Attribute $attribute */
        foreach ($iavr->resolveInitialAttributes($assetMock) as $attribute) {
            $n = $attribute->getDefinition()->getName();
            $result[$n] ??= [];
            $l = $attribute->getLocale();
            $result[$n][$l] ??= [];
            $result[$n][$l][] = $attribute->getValue();
        }

        $this->assertEquals($this->normalizeExpected($expected), $result);
    }

    private function normalizeExpected(array $expected): array
    {
        $normalized = [];
        foreach ($expected as $attributeName => $value) {
            if (is_array($value)) {
                if ($this->isNumericArray($value)) {
                    // a simple list of values
                    $normalized[$attributeName] = ['_' => $value];
                } else {
                    // an array with key=locale
                    $normalized[$attributeName] = array_map(
                        fn ($v) => is_array($v) ? $v : [$v],
                        $value
                    );
                }
            } else {
                // a single value
                $normalized[$attributeName] = ['_' => [trim($value)]];
            }
        }

        return $normalized;
    }

    private function isNumericArray($a): bool
    {
        if (!is_array($a)) {
            return false;
        }

        return array_all($a, fn ($v, $k) => is_numeric($k));
    }

    private function normalizeMetadata($data): array
    {
        if (null === $data) {
            return [];
        }
        $normalized = [];
        $data = is_array($data) ? $data : [$data];
        foreach ($data as $key => $value) {
            $values = is_array($value) ? array_values($value) : [$value];
            [$group, $tag] = explode(':', (string) $key, 2);
            $normalized[$group][$tag] = $values;
        }

        return $normalized;
    }
}
