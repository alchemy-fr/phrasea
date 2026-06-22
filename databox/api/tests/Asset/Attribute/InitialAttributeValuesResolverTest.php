<?php

declare(strict_types=1);

namespace App\Tests\Asset\Attribute;

use App\Attribute\AttributeAssigner;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\InitialAttributeValuesResolver;
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
                Yaml::parseFile(__DIR__.'/../../../src/Documentation/InitialAttributeValuesResolverData.yaml'),
                fn ($test) => $test['test'] ?? true
            )
        );
    }

    /**
     * @dataProvider dataProvider
     *
     * @param array<string, string|string[]> $metadata
     */
    public function testResolveInitialAttributes(array $definitions, ?array $metadata, array $expected): void
    {
        $attributeDefinitions = [];
        foreach ($definitions as $name => $definition) {
            if (null !== ($initialValues = $definition['initialValues'] ?? null)) {
                $initialValues = is_array($initialValues) ? $initialValues : ['_' => $initialValues];
            }
            $ad = $this->createMock(AttributeDefinition::class);
            $ad->expects($this->any())->method('getName')
                ->willReturn($name);
            $ad->expects($this->any())->method('isMultiple')
                ->willReturn($definition['isMultiple'] ?? false);
            $ad->expects($this->any())->method('isTranslatable')
                ->willReturn($definition['isTranslatable'] ?? false);
            $ad->expects($this->any())->method('getInitialValues')
                ->willReturn($initialValues);
            $ad->expects($this->any())->method('getType')
                ->willReturn($definition['type'] ?? TextAttributeType::NAME);
            $attributeDefinitions[] = $ad;
        }

        $adr = $this->createMock(AttributeDefinitionRepository::class);
        $adr->expects($this->any())
            ->method('getWorkspaceInitializeDefinitions')
            ->willReturn($attributeDefinitions);

        $fileMock = $this->createMock(File::class);

        $fileMock->expects($this->any())
            ->method('getMetadata')
            ->willReturn($this->normalizeMetadata($metadata));

        $assetMock = $this->createMock(Asset::class);
        $assetMock
            ->expects($this->any())
            ->method('getSource')
            ->willReturn($fileMock);
        $assetMock
            ->expects($this->any())
            ->method('getId')
            ->willReturn(Uuid::uuid4()->toString());

        $iavr = new InitialAttributeValuesResolver(
            $adr,
            $this->attributeAssigner
        );

        $result = [];
        /** @var Attribute $attribute */
        foreach ($iavr->resolveInitialAttributes($assetMock) as $attribute) {
            $result[$attribute->getDefinition()->getName()] ??= [];
            $result[$attribute->getDefinition()->getName()][$attribute->getLocale()] ??= [];
            $result[$attribute->getDefinition()->getName()][$attribute->getLocale()][] = $attribute->getValue();
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
                $normalized[$attributeName] = ['_' => [$value]];
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
            if (is_array($value)) {
                $normalized[$key] = [
                    'value' => join(' ; ', $value),
                    'values' => $value,
                ];
            } else {
                $normalized[$key] = [
                    'value' => $value,
                    'values' => [$value],
                ];
            }
        }

        return $normalized;
    }
}
