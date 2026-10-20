<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeEntity;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * GET /attribute-entities?usage=1&status=…: the entities with the number of values referencing them,
 * and the pending values open to the attribute editors of the workspace.
 */
class AttributeEntityUsageTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    public function testUsageAndStatus(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->addUserOnWorkspace(KeycloakClientTestMock::USER_UID, $workspace->getId());
        $list = $this->createEntityList(['name' => 'Persons', 'workspace' => $workspace]);
        $definition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Persons', 'slug' => 'persons', 'multiple' => true, 'type' => EntityAttributeType::NAME, 'list' => $list]);

        $alice = $this->createEntity($list, 'Alice');
        $unknown = $this->createEntity($list, 'Unknown person · a1b2', ['status' => AttributeEntity::STATUS_PENDING]);
        $asset = $this->createAsset(['workspace' => $workspace, 'public' => true]);
        foreach ([[$alice, Attribute::ORIGIN_HUMAN], [$alice, Attribute::ORIGIN_MACHINE], [$unknown, Attribute::ORIGIN_MACHINE]] as [$entity, $origin]) {
            $attribute = new Attribute();
            $attribute->setAsset($asset);
            $attribute->setDefinition($definition);
            $attribute->setValue($entity->getId());
            $attribute->setOrigin($origin);
            $em->persist($attribute);
        }
        $em->flush();

        $list_ = fn (string $userId, array $query): array => $this->api('GET', '/attribute-entities', $userId, options: ['query' => array_merge(['list' => $list->getId()], $query)])->toArray()['member'];

        $members = $list_(KeycloakClientTestMock::ADMIN_UID, ['usage' => 1]);
        $usage = array_column($members, 'usage', 'id');
        $this->assertSame(['attributes' => 2, 'humanAttributes' => 1], $usage[$alice->getId()]);
        $this->assertSame(['attributes' => 1, 'humanAttributes' => 0], $usage[$unknown->getId()]);

        $this->assertSame([$unknown->getId()], array_column($list_(KeycloakClientTestMock::ADMIN_UID, ['status' => AttributeEntity::STATUS_PENDING]), 'id'));
        $this->assertSame([$alice->getId()], array_column($list_(KeycloakClientTestMock::ADMIN_UID, ['status' => AttributeEntity::STATUS_APPROVED]), 'id'));
        $this->assertArrayNotHasKey('usage', $list_(KeycloakClientTestMock::ADMIN_UID, [])[0]);

        // A member who cannot edit the attributes of the workspace does not see the pending values...
        $this->assertSame([$alice->getId()], array_column($list_(KeycloakClientTestMock::USER_UID, []), 'id'));
        $this->api('GET', '/attribute-entities/'.$unknown->getId(), KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(403);

        // ...but an attribute editor of the workspace does, to name them
        $this->grantUserOnObject(KeycloakClientTestMock::USER_UID, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_EDIT);
        $this->assertEqualsCanonicalizing([$alice->getId(), $unknown->getId()], array_column($list_(KeycloakClientTestMock::USER_UID, ['workspace' => $workspace->getId()]), 'id'));
        $this->api('GET', '/attribute-entities/'.$unknown->getId(), KeycloakClientTestMock::USER_UID);
        $this->assertResponseStatusCodeSame(200);
    }
}
