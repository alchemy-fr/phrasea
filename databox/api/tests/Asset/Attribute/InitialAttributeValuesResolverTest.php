<?php

declare(strict_types=1);

namespace App\Tests\Asset\Attribute;

use App\Asset\Attribute\InitialAttributeValuesResolver;
use App\Attribute\AttributeAssigner;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\Repository\Core\AttributeDefinitionRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InitialAttributeValuesResolverTest extends KernelTestCase
{
    public static function dataProvider(): array
    {
        return [
            'test1' => [
                'definition' => [
                    'name' => 'Title',
                    'isMultiple' => false,
                    'initialValues' => [
                        '_' => '{ "type": "metadata", "value": "XMP-dc:Title"}',
                        'en' => '{ "type": "metadata", "value": "description"}',
                    ],
                    'fieldType' => TextAttributeType::NAME,
                ],
                'metadata' => [
                    'Composite:GPSPosition' => '48.8588443, 2.2943506',
                    'XMP-dc:Title' => 'Test Title',
                    'description' => 'Test Description',
                ],
                'expected' => [
                    'Title' => [
                        '_' => ['Test Title'],
                        'en' => ['Test Description'],
                    ],
                ],
            ],
            'test2' => [
                'definition' => [
                    'name' => 'Keywords',
                    'isMultiple' => true,
                    'initialValues' => [
                        '_' => '{ "type": "metadata", "value": "IPTC:Keywords"}',
                    ],
                    'fieldType' => TextAttributeType::NAME,
                ],
                'metadata' => [
                    'IPTC:Keywords' => ['dog', 'cat', 'bird'],
                ],
                'expected' => [
                    'Keywords' => [
                        '_' => ['cat', 'dog', 'bird'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $metadata
     */
    public function testResolveInitialAttributes(array $definition, array $metadata, array $expected): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $atr = $this->createMock(AttributeDefinition::class);
        $atr->expects($this->any())->method('getName')
                ->willReturn($definition['name']);
        $atr->expects($this->any())->method('isMultiple')
                ->willReturn($definition['isMultiple']);
        $atr->expects($this->any())->method('getInitialValues')
                ->willReturn($definition['initialValues']);
        $atr->expects($this->any())->method('getFieldType')
                ->willReturn($definition['fieldType']);

        /** @var AttributeDefinitionRepository $adr */
        $adr = $this->createMock(AttributeDefinitionRepository::class);
        $adr->expects($this->any())
            ->method('getWorkspaceInitializeDefinitions')
            ->willReturn([$atr]);

        /** @var File $fileMock */
        $fileMock = $this->createMock(File::class);

        $data = is_array($metadata) ? $metadata : [$metadata];
        $fileMock->expects($this->any())
            ->method('getMetadata')
            ->willReturn($this->toMetadata($metadata));

        /** @var Asset $assetMock */
        $assetMock = $this->createMock(Asset::class);
        $assetMock->expects($this->any())
            ->method('getSource')
            ->willReturn($fileMock);

        // ================================================

        $iavr = new InitialAttributeValuesResolver(
            $adr,
            $container->get(AttributeAssigner::class)
        );

        $result = [];
        /** @var Attribute $attribute */
        foreach ($iavr->resolveInitialAttributes($assetMock) as $attribute) {
            $result[$attribute->getDefinition()->getName()] ??= [];
            $result[$attribute->getDefinition()->getName()][$attribute->getLocale()] ??= [];
            $result[$attribute->getDefinition()->getName()][$attribute->getLocale()][] = $attribute->getValue();
        }

        $this->assertEqualsCanonicalizing($expected, $result);
    }

    private function toMetadata($data)
    {
        $metadata = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $metadata[$key] = [
                    'value' => join(' ; ', $value),
                    'values' => $value,
                ];
            } else {
                $metadata[$key] = [
                    'value' => $value,
                    'values' => [$value],
                ];
            }
        }

        return $metadata;
    }
}
