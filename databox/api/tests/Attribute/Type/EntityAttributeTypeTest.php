<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\AttributeInterface;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;

class EntityAttributeTypeTest extends AbstractAttributeTypeTest
{
    private AttributeEntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AttributeEntityRepository::class);
    }

    protected function getType(): AttributeTypeInterface
    {
        return new EntityAttributeType($this->repository);
    }

    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            ...parent::getElasticsearchNormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
        ];
    }

    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            ['foo', ['Invalid entity ID']],
            ['17e9152c-fa5f-4f53-83ac-62674f6f6f8d', null],
        ];
    }

    public function testNormalizeValueUsesEntityId(): void
    {
        $entity = $this->createMock(AttributeEntity::class);
        $entity->method('getId')->willReturn('17e9152c-fa5f-4f53-83ac-62674f6f6f8d');

        $this->assertSame('17e9152c-fa5f-4f53-83ac-62674f6f6f8d', $this->getType()->convertToDbValue($entity));
    }

    public function testNormalizeElasticsearchValueReturnsNullWhenEntityIsMissing(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->assertNull($this->getType()->normalizeElasticsearchValue('17e9152c-fa5f-4f53-83ac-62674f6f6f8d'));
    }

    public function testNormalizeElasticsearchValueBuildsPayloadForApprovedEntity(): void
    {
        $entity = $this->createMock(AttributeEntity::class);
        $entity->method('isApproved')->willReturn(true);
        $entity->method('getTranslations')->willReturn(['fr' => 'Bonjour']);
        $entity->method('getValue')->willReturn('Hello');
        $entity->method('getId')->willReturn('17e9152c-fa5f-4f53-83ac-62674f6f6f8d');
        $entity->method('getSynonyms')->willReturn(['fr' => ['salut']]);

        $this->repository->method('find')->willReturn($entity);

        $this->assertEquals([
            'fr' => [
                'id' => '17e9152c-fa5f-4f53-83ac-62674f6f6f8d',
                'value' => 'Bonjour',
                'synonyms' => ['salut'],
            ],
            AttributeInterface::NO_LOCALE => [
                'id' => '17e9152c-fa5f-4f53-83ac-62674f6f6f8d',
                'value' => 'Hello',
            ],
        ], $this->getType()->normalizeElasticsearchValue('17e9152c-fa5f-4f53-83ac-62674f6f6f8d'));
    }
}
