<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Attribute\Type\EntityAttributeType;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * Rights given on a single entity list (ACEs of type "entity_list"), on top of the workspace membership:
 * OPERATOR edits the list properties and its values, EDIT manages the values only.
 * Creating, deleting the list and managing its ACEs stay with the workspace editors.
 *
 * The clear and merge operations patch the Elasticsearch documents, hence the search test case.
 */
final class EntityListAclTest extends AbstractSearchTestCase
{
    use AttributeApiTestTrait;

    private Workspace $workspace;
    private EntityList $list;

    /**
     * USER owns (hence edits) the workspace, OTHER is a mere member unless $member is false.
     */
    private function setUpScene(?int $listMask, bool $member = true): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        if ($member) {
            $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        }
        $this->list = $this->createEntityList(['name' => 'Colors']);
        if (null !== $listMask) {
            $this->grantUserOnObject(self::OTHER, $this->list, $listMask);
        }
    }

    private function iri(?EntityList $list = null): string
    {
        return '/entity-lists/'.($list ?? $this->list)->getId();
    }

    private function postValue(string $userId, string $value, ?EntityList $list = null): array
    {
        return $this->api('POST', '/attribute-entities', $userId, [
            'list' => $this->iri($list),
            'value' => $value,
        ])->toArray(false);
    }

    /**
     * The API calls reset the entity manager: the list has to be loaded again before being associated.
     */
    private function reloadList(): EntityList
    {
        return $this->list = self::getEntityManager()->find(EntityList::class, $this->list->getId());
    }

    private function findEntity(string $id): ?AttributeEntity
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(AttributeEntity::class, $id);
    }

    private function putListAce(string $as, string $userId, int $mask): array
    {
        return $this->api('PUT', '/permissions/ace', $as, [
            'objectType' => EntityList::OBJECT_TYPE,
            'objectId' => $this->list->getId(),
            'userType' => 'user',
            'userId' => $userId,
            'mask' => $mask,
        ])->toArray(false);
    }

    private function assertValueCrud(string $userId): void
    {
        $data = $this->postValue($userId, 'Red');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $data['status'], 'the values of a list manager are approved');

        $this->api('PATCH', '/attribute-entities/'.$data['id'], $userId, ['value' => 'Dark red']);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['value' => 'Dark red']);

        $this->api('DELETE', '/attribute-entities/'.$data['id'], $userId);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findEntity($data['id']));
    }

    private function assertImportExportAndClear(string $userId): void
    {
        $this->api('POST', $this->iri().'/import', $userId, ['format' => 'raw', 'data' => "Blue\nGreen"]);
        $this->assertResponseIsSuccessful();
        $this->api('POST', $this->iri().'/export', $userId, ['format' => 'csv']);
        $this->assertResponseIsSuccessful();
        $this->api('POST', $this->iri().'/clear', $userId);
        $this->assertResponseIsSuccessful();
    }

    private function assertWorkspaceEditorOnlyOperationsAreDenied(string $userId): void
    {
        $this->api('DELETE', $this->iri(), $userId);
        $this->assertResponseStatusCodeSame(403);
        $this->api('POST', '/entity-lists', $userId, [
            'workspace' => '/workspaces/'.$this->workspace->getId(),
            'name' => 'Shapes',
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->putListAce($userId, self::OTHER, PermissionInterface::OPERATOR);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/permissions/aces', $userId, options: ['query' => [
            'objectType' => EntityList::OBJECT_TYPE,
            'objectId' => $this->list->getId(),
        ]]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnOperatorEditsTheListAndItsValues(): void
    {
        $this->setUpScene(PermissionInterface::OPERATOR);

        $this->api('PATCH', $this->iri(), self::OTHER, ['name' => 'Palette', 'withEmojis' => true]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Palette', 'withEmojis' => true]);

        $this->assertValueCrud(self::OTHER);
        $this->assertImportExportAndClear(self::OTHER);
        $this->assertWorkspaceEditorOnlyOperationsAreDenied(self::OTHER);
    }

    public function testAnOperatorCannotMoveTheList(): void
    {
        $this->setUpScene(PermissionInterface::OPERATOR);
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $otherWorkspace->getId());

        $this->api('PATCH', $this->iri(), self::OTHER, ['workspace' => '/workspaces/'.$otherWorkspace->getId()]);
        $this->assertResponseStatusCodeSame(400);
        self::getEntityManager()->clear();
        $this->assertSame($this->workspace->getId(), $this->reloadList()->getWorkspaceId());
    }

    public function testAValueEditorManagesTheValuesOnly(): void
    {
        $this->setUpScene(PermissionInterface::EDIT);

        $this->api('PATCH', $this->iri(), self::OTHER, ['name' => 'Palette']);
        $this->assertResponseStatusCodeSame(403);

        $this->assertValueCrud(self::OTHER);

        // Moderation of the proposals
        $pending = $this->createEntity($this->reloadList(), 'Proposal', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => 'someone']);
        $this->api('PATCH', '/attribute-entities/'.$pending->getId(), self::OTHER, ['status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $this->findEntity($pending->getId())->getStatus());

        $main = $this->createEntity($this->reloadList(), 'Main');
        $duplicate = $this->createEntity($this->list, 'Duplicate');
        $this->api('PUT', '/attribute-entities/'.$main->getId().'/merge', self::OTHER, ['ids' => [$duplicate->getId()]]);
        $this->assertResponseIsSuccessful();
        $this->assertNull($this->findEntity($duplicate->getId()));

        $this->assertImportExportAndClear(self::OTHER);
        $this->assertWorkspaceEditorOnlyOperationsAreDenied(self::OTHER);
    }

    public function testListRightsRequireTheWorkspaceMembership(): void
    {
        $this->setUpScene(PermissionInterface::OPERATOR | PermissionInterface::EDIT, member: false);

        $response = $this->api('GET', '/entity-lists', self::OTHER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([], $response->toArray()['member']);

        $this->api('GET', $this->iri(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('PATCH', $this->iri(), self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->postValue(self::OTHER, 'Red');
        $this->assertResponseStatusCodeSame(403);
        $entity = $this->createEntity($this->reloadList(), 'Blue');
        $this->api('PATCH', '/attribute-entities/'.$entity->getId(), self::OTHER, ['value' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        foreach (['import' => ['format' => 'raw', 'data' => 'Red'], 'export' => ['format' => 'csv'], 'clear' => null] as $operation => $payload) {
            $this->api('POST', $this->iri().'/'.$operation, self::OTHER, $payload);
            $this->assertResponseStatusCodeSame(403, $operation);
        }
    }

    public function testAListAceOpensNothingOnAnotherList(): void
    {
        $this->setUpScene(PermissionInterface::OPERATOR);
        $other = $this->createEntityList(['name' => 'Shapes']);
        $square = $this->createEntity($other, 'Square');

        $this->api('PATCH', $this->iri($other), self::OTHER, ['name' => 'Hacked']);
        $this->assertResponseStatusCodeSame(403);
        $this->postValue(self::OTHER, 'Circle', $other);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/attribute-entities/'.$square->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('POST', $this->iri($other).'/import', self::OTHER, ['format' => 'raw', 'data' => 'Circle']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheListOwnerGetsNoRightFromTheOwnership(): void
    {
        $this->setUpScene(null);
        $this->list->setOwnerId(self::OTHER);
        self::getEntityManager()->flush();

        $this->api('PATCH', $this->iri(), self::OTHER, ['name' => 'Palette']);
        $this->assertResponseStatusCodeSame(403);
        $this->postValue(self::OTHER, 'Red');
        $this->assertResponseStatusCodeSame(403);
        $this->api('POST', $this->iri().'/import', self::OTHER, ['format' => 'raw', 'data' => 'Red']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAWorkspaceEditorManagesTheListAces(): void
    {
        $this->setUpScene(null);

        $ace = $this->putListAce(self::USER, self::OTHER, PermissionInterface::EDIT);
        $this->assertResponseIsSuccessful();
        $this->assertSame(EntityList::OBJECT_TYPE, $ace['objectType']);
        $this->assertSame($this->list->getId(), $ace['objectId']);

        $response = $this->api('GET', '/permissions/aces', self::USER, options: ['query' => [
            'objectType' => EntityList::OBJECT_TYPE,
            'objectId' => $this->list->getId(),
        ]]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([self::OTHER => PermissionInterface::EDIT], array_column($response->toArray(), 'mask', 'userId'));

        // The granted member now manages the values
        $this->postValue(self::OTHER, 'Red');
        $this->assertResponseStatusCodeSame(201);

        // The ACEs go with the list
        $this->api('DELETE', $this->iri(), self::USER);
        $this->assertResponseStatusCodeSame(204);
        $this->assertSame([], self::getPermissionManager()->findAces(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            self::OTHER,
            EntityList::OBJECT_TYPE,
            $this->list->getId(),
        ));
    }

    public function testCapabilities(): void
    {
        $this->setUpScene(null);
        $operatorList = $this->list;
        $this->grantUserOnObject(self::OTHER, $operatorList, PermissionInterface::OPERATOR);
        $valuesList = $this->createEntityList(['name' => 'Shapes']);
        $this->grantUserOnObject(self::OTHER, $valuesList, PermissionInterface::EDIT);
        $readOnlyList = $this->createEntityList(['name' => 'Sizes']);

        $expected = [
            self::USER => [
                'Colors' => ['edit' => true, 'editValues' => true, 'delete' => true, 'editPermissions' => true],
                'Shapes' => ['edit' => true, 'editValues' => true, 'delete' => true, 'editPermissions' => true],
                'Sizes' => ['edit' => true, 'editValues' => true, 'delete' => true, 'editPermissions' => true],
            ],
            self::OTHER => [
                'Colors' => ['edit' => true, 'editValues' => true, 'delete' => false, 'editPermissions' => false],
                'Shapes' => ['edit' => false, 'editValues' => true, 'delete' => false, 'editPermissions' => false],
                'Sizes' => ['edit' => false, 'editValues' => false, 'delete' => false, 'editPermissions' => false],
            ],
        ];
        $lists = ['Colors' => $operatorList, 'Shapes' => $valuesList, 'Sizes' => $readOnlyList];

        foreach ($expected as $userId => $capabilitiesByList) {
            $response = $this->api('GET', '/entity-lists', $userId);
            $this->assertResponseStatusCodeSame(200);
            $this->assertSame(
                $capabilitiesByList,
                array_column($response->toArray()['member'], 'capabilities', 'name'),
            );

            foreach ($capabilitiesByList as $name => $capabilities) {
                $response = $this->api('GET', $this->iri($lists[$name]), $userId);
                $this->assertResponseStatusCodeSame(200);
                $this->assertSame($capabilities, $response->toArray()['capabilities']);
            }
        }
    }

    public function testANestedListGoesWithoutCapabilities(): void
    {
        $this->setUpScene(null);
        $definition = $this->createAttributeDefinition([
            'name' => 'Color',
            'type' => EntityAttributeType::NAME,
            'list' => $this->list,
        ]);

        $response = $this->api('GET', '/attribute-definitions/'.$definition->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
        $entityList = $response->toArray()['entityList'];
        $this->assertSame($this->iri(), $entityList['@id'] ?? $entityList);
        if (\is_array($entityList)) {
            $this->assertArrayNotHasKey('capabilities', $entityList);
        }
    }
}
