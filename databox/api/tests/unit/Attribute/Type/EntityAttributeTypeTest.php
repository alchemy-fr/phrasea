<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute\Type;

use App\Attribute\AttributeInterface;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\Workspace;
use App\Http\LocaleContext;
use App\Repository\Core\AttributeEntityRepository;
use App\Security\Voter\AbstractVoter;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class EntityAttributeTypeTest extends AbstractAttributeTypeTestCase
{
    private const string ENTITY_ID = '17e9152c-fa5f-4f53-83ac-62674f6f6f8d';

    private AttributeEntityRepository&MockObject $repository;
    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AttributeEntityRepository::class);
        $this->security = $this->createMock(Security::class);
    }

    protected function getType(): AttributeTypeInterface
    {
        $type = new EntityAttributeType($this->repository, $this->security);
        $type->setLocaleContext(new LocaleContext(new RequestStack()));

        return $type;
    }

    #[\Override]
    public static function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
        ];
    }

    #[\Override]
    public static function getElasticsearchNormalizationCases(): array
    {
        return [
            ...parent::getElasticsearchNormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
        ];
    }

    #[\Override]
    public static function getValidationCases(): array
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

    public function testDenormalizeValueShowsApprovedValue(): void
    {
        $this->createEntity(AttributeEntity::STATUS_APPROVED);
        $this->security->expects($this->never())->method('isGranted');

        $this->assertSame('Alice', $this->getType()->denormalizeValue(self::ENTITY_ID)['value']);
    }

    public function testDenormalizeValueHidesPendingValueFromWhoCannotEditIt(): void
    {
        $entity = $this->createEntity(AttributeEntity::STATUS_PENDING);
        $this->security->expects($this->once())->method('isGranted')->with(AbstractVoter::EDIT, $entity)->willReturn(false);

        $denormalized = $this->getType()->denormalizeValue(self::ENTITY_ID);
        $this->assertNull($denormalized['value']);
        $this->assertSame(AttributeEntity::STATUS_PENDING, $denormalized['status']);
    }

    public function testDenormalizeValueShowsPendingValueToWhoCanEditIt(): void
    {
        $entity = $this->createEntity(AttributeEntity::STATUS_PENDING);
        $this->security->expects($this->once())->method('isGranted')->with(AbstractVoter::EDIT, $entity)->willReturn(true);

        $this->assertSame('Alice', $this->getType()->denormalizeValue(self::ENTITY_ID)['value']);
    }

    private function createEntity(int $status): AttributeEntity
    {
        $entity = new AttributeEntity();
        $entity->setWorkspace(new Workspace());
        $entity->setValue('Alice');
        $entity->setStatus($status);
        $this->repository->method('find')->with(self::ENTITY_ID)->willReturn($entity);

        return $entity;
    }
}
