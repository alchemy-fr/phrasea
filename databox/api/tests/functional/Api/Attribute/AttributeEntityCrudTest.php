<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /attribute-entities: the values of an entity list (a controlled vocabulary).
 *
 * The list editors (workspace editors) manage the values. Members may propose new
 * values when the list allows it: they stay pending until approved, unless the
 * list approves new values automatically.
 */
final class AttributeEntityCrudTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private Workspace $workspace;
    private EntityList $list;

    /**
     * USER owns (hence edits) the workspace, OTHER is a mere member.
     */
    private function setUpScene(array $listOptions = []): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->list = $this->createEntityList(array_merge(['name' => 'Colors'], $listOptions));
    }

    private function listValues(?string $userId, array $query = []): array
    {
        $response = $this->api('GET', '/attribute-entities', $userId, options: ['query' => $query]);
        $this->assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['hydra:member'], 'value');
    }

    private function postEntity(string $userId, array $data): array
    {
        return $this->api('POST', '/attribute-entities', $userId, array_merge([
            'list' => '/entity-lists/'.$this->list->getId(),
        ], $data))->toArray(false);
    }

    private function findEntity(string $id): ?AttributeEntity
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(AttributeEntity::class, $id);
    }

    public function testListOnlyShowsApprovedValuesAndOwnProposals(): void
    {
        $this->setUpScene();
        $this->createEntity($this->list, 'Approved');
        $this->createEntity($this->list, 'Pending by OTHER', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);
        $this->createEntity($this->list, 'Pending by someone', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => 'someone']);
        $this->createEntity($this->list, 'Rejected', ['status' => AttributeEntity::STATUS_REJECTED, 'creatorId' => 'someone']);

        $this->assertEqualsCanonicalizing(['Approved', 'Pending by OTHER'], $this->listValues(self::OTHER));

        // Without the list filter, even an editor only sees approved values
        $this->assertEqualsCanonicalizing(['Approved'], $this->listValues(self::USER));

        // A list editor filtering on the list sees every status
        $this->assertEqualsCanonicalizing(
            ['Approved', 'Pending by OTHER', 'Pending by someone', 'Rejected'],
            $this->listValues(self::USER, ['list' => $this->list->getId()]),
        );
        // ...not a mere member
        $this->assertEqualsCanonicalizing(
            ['Approved', 'Pending by OTHER'],
            $this->listValues(self::OTHER, ['list' => $this->list->getId()]),
        );
    }

    public function testListIsRestrictedToReadableWorkspaces(): void
    {
        $this->setUpScene();
        $this->createEntity($this->list, 'Mine');
        $foreignWorkspace = $this->createOtherWorkspace();
        $foreignList = $this->createEntityList(['name' => 'Foreign', 'workspace' => $foreignWorkspace]);
        $this->createEntity($foreignList, 'Foreign value');

        $this->assertSame(['Mine'], $this->listValues(self::OTHER));
        $this->assertEqualsCanonicalizing(['Mine', 'Foreign value'], $this->listValues(self::ADMIN));
        $this->assertSame([], $this->listValues(null), 'anonymous only reads public workspaces');

        $this->api('GET', '/attribute-entities', self::OTHER, options: ['query' => [
            'workspace' => '/workspaces/'.$foreignWorkspace->getId(),
        ]]);
        $this->assertResponseStatusCodeSame(403);

        $this->api('GET', '/attribute-entities', self::OTHER, options: ['query' => [
            'workspace' => '9b2a7f5e-0000-4000-8000-000000000000',
        ]]);
        $this->assertResponseStatusCodeSame(400);

        $this->api('GET', '/attribute-entities', self::OTHER, options: ['query' => [
            'list' => '9b2a7f5e-0000-4000-8000-000000000000',
        ]]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListFiltersAndOrder(): void
    {
        $this->setUpScene();
        $otherList = $this->createEntityList(['name' => 'Shapes']);
        $this->createEntity($this->list, 'Dark blue');
        $this->createEntity($this->list, 'Red');
        $this->createEntity($this->list, 'Light blue');
        $this->createEntity($otherList, 'Blue square');

        $this->assertSame(['Dark blue', 'Light blue', 'Red'], $this->listValues(self::OTHER, [
            'list' => $this->list->getId(),
            'order' => ['value' => 'asc'],
        ]));
        $this->assertSame(['Red', 'Light blue', 'Dark blue'], $this->listValues(self::OTHER, [
            'list' => $this->list->getId(),
            'order' => ['value' => 'desc'],
        ]));
        $this->assertSame(['Blue square', 'Dark blue', 'Light blue'], $this->listValues(self::OTHER, [
            'value' => 'BLUE',
            'order' => ['value' => 'asc'],
        ]));
    }

    public function testGetItem(): void
    {
        $this->setUpScene();
        $entity = $this->createEntity($this->list, 'Red', [
            'translations' => ['fr' => 'Rouge'],
            'synonyms' => ['en' => ['Crimson']],
        ]);

        $this->api('GET', '/attribute-entities/'.$entity->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            '@type' => 'attribute-entity',
            'id' => $entity->getId(),
            'value' => 'Red',
            'status' => AttributeEntity::STATUS_APPROVED,
            'translations' => ['fr' => 'Rouge'],
            'synonyms' => ['en' => ['Crimson']],
        ]);
    }

    public function testGetItemIsRestricted(): void
    {
        $this->setUpScene();
        $pending = $this->createEntity($this->list, 'Pending', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => 'someone']);
        $foreignList = $this->createEntityList(['name' => 'Foreign', 'workspace' => $this->createOtherWorkspace()]);
        $foreign = $this->createEntity($foreignList, 'Foreign value');

        $this->api('GET', '/attribute-entities/'.$foreign->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->api('GET', '/attribute-entities/'.$foreign->getId());
        $this->assertResponseStatusCodeSame(401);
        $this->api('GET', '/attribute-entities/'.$pending->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        // The list editor moderates the proposals of others
        $this->api('GET', '/attribute-entities/'.$pending->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAnEditorCreatesApprovedValues(): void
    {
        $this->setUpScene();

        $data = $this->postEntity(self::USER, [
            'value' => 'Red',
            'translations' => ['fr-FR' => 'Rouge', 'de' => ''],
            'synonyms' => ['en' => ['Crimson', 'Scarlet']],
            'color' => '#ff0000',
            'emoji' => '🔴',
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Red', $data['value']);
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $data['status']);
        $this->assertSame(['fr_FR' => 'Rouge'], $data['translations'], 'locales are normalized and empty translations dropped');
        $this->assertSame(['en' => ['Crimson', 'Scarlet']], $data['synonyms']);
        $this->assertSame('#ff0000', $data['color']);
        $this->assertSame('🔴', $data['emoji']);

        $entity = $this->findEntity($data['id']);
        $this->assertSame(self::USER, $entity->getCreatorId());
        $this->assertSame($this->workspace->getId(), $entity->getWorkspaceId(), 'the workspace is taken from the list');
    }

    public function testAMemberCannotAddValuesToAClosedList(): void
    {
        $this->setUpScene(['allowNewValues' => false]);

        $this->postEntity(self::OTHER, ['value' => 'Red']);
        $this->assertResponseStatusCodeSame(403);

        $this->api('POST', '/attribute-entities', null, [
            'list' => '/entity-lists/'.$this->list->getId(),
            'value' => 'Red',
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testOnlyMembersProposeValuesToAnOpenList(): void
    {
        $this->workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::USER]);
        $this->list = $this->createEntityList(['name' => 'Colors', 'allowNewValues' => true]);

        $this->postEntity(self::OTHER, ['value' => 'Spam']);
        $this->assertResponseStatusCodeSame(403);

        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
        $this->postEntity(self::OTHER, ['value' => 'Red']);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testAMemberProposalIsPendingUntilApproved(): void
    {
        $this->setUpScene(['allowNewValues' => true, 'approveNewValues' => false]);

        // Even when trying to force the status
        $data = $this->postEntity(self::OTHER, ['value' => 'Red', 'status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(AttributeEntity::STATUS_PENDING, $data['status']);
        $this->assertSame(self::OTHER, $this->findEntity($data['id'])->getCreatorId());

        // The editor approves it
        $this->api('PUT', '/attribute-entities/'.$data['id'], self::USER, ['status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $this->findEntity($data['id'])->getStatus());
    }

    public function testAMemberProposalIsApprovedWhenTheListApprovesNewValues(): void
    {
        $this->setUpScene(['allowNewValues' => true, 'approveNewValues' => true]);

        $data = $this->postEntity(self::OTHER, ['value' => 'Red']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(AttributeEntity::STATUS_APPROVED, $data['status']);
    }

    public function testCreateValidation(): void
    {
        $this->setUpScene();
        $this->createEntity($this->list, 'Red');
        $otherList = $this->createEntityList(['name' => 'Others']);

        $this->postEntity(self::USER, ['value' => 'Red']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'value', 'message' => 'This value already exists in the list']]]);

        $this->postEntity(self::USER, ['value' => '']);
        $this->assertResponseStatusCodeSame(422);

        $this->postEntity(self::USER, ['value' => 'Blue', 'color' => 'not-a-color']);
        $this->assertResponseStatusCodeSame(422);

        $this->postEntity(self::USER, ['value' => 'Blue', 'emoji' => '🔵🔵']);
        $this->assertResponseStatusCodeSame(422);

        $this->postEntity(self::USER, ['value' => 'Blue', 'synonyms' => ['en' => [str_repeat('a', 101)]]]);
        $this->assertResponseStatusCodeSame(422);

        // The same value is accepted in another list
        $this->api('POST', '/attribute-entities', self::USER, [
            'list' => '/entity-lists/'.$otherList->getId(),
            'value' => 'Red',
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testTheWorkspaceMustMatchTheListOne(): void
    {
        $this->setUpScene();
        $otherWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);

        $this->api('POST', '/attribute-entities', self::USER, [
            'workspace' => '/workspaces/'.$otherWorkspace->getId(),
            'list' => '/entity-lists/'.$this->list->getId(),
            'value' => 'Red',
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testAnEditorUpdatesAndDeletesAnyValue(): void
    {
        $this->setUpScene();
        $entity = $this->createEntity($this->list, 'Red', ['creatorId' => 'someone']);
        $iri = '/attribute-entities/'.$entity->getId();

        $this->api('PUT', $iri, self::USER, ['value' => 'Dark red', 'translations' => ['fr' => 'Rouge foncé']]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['value' => 'Dark red', 'translations' => ['fr' => 'Rouge foncé']]);

        $this->api('PATCH', $iri, self::USER, ['color' => '#880000']);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['value' => 'Dark red', 'color' => '#880000']);

        $this->api('DELETE', $iri, self::USER);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findEntity($entity->getId()));
    }

    public function testAMemberCannotChangeAnApprovedValue(): void
    {
        $this->setUpScene(['allowNewValues' => true]);
        $mine = $this->createEntity($this->list, 'Mine', ['creatorId' => self::OTHER]);
        $iri = '/attribute-entities/'.$mine->getId();

        $this->api('PUT', $iri, self::OTHER, ['value' => 'Changed']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('PATCH', $iri, self::OTHER, ['value' => 'Changed']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', $iri, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testACreatorManagesItsPendingProposal(): void
    {
        $this->setUpScene(['allowNewValues' => true]);
        $mine = $this->createEntity($this->list, 'Mine', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);
        $notMine = $this->createEntity($this->list, 'Not mine', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => 'someone']);

        $this->api('PUT', '/attribute-entities/'.$notMine->getId(), self::OTHER, ['value' => 'Changed']);
        $this->assertResponseStatusCodeSame(403);
        $this->api('DELETE', '/attribute-entities/'.$notMine->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->api('PUT', '/attribute-entities/'.$mine->getId(), self::OTHER, ['value' => 'Mine, fixed']);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['value' => 'Mine, fixed']);

        $this->api('DELETE', '/attribute-entities/'.$mine->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testACreatorCannotApproveItsOwnProposal(): void
    {
        $this->setUpScene(['allowNewValues' => true]);
        $mine = $this->createEntity($this->list, 'Mine', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);

        $this->api('PUT', '/attribute-entities/'.$mine->getId(), self::OTHER, ['status' => AttributeEntity::STATUS_APPROVED]);
        $this->assertSame(AttributeEntity::STATUS_PENDING, $this->findEntity($mine->getId())->getStatus());
    }

    public function testACreatorCannotMoveItsProposalToAnotherList(): void
    {
        $this->setUpScene(['allowNewValues' => true]);
        $closed = $this->createEntityList(['name' => 'Closed', 'allowNewValues' => false]);
        $mine = $this->createEntity($this->list, 'Mine', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);

        $this->api('PUT', '/attribute-entities/'.$mine->getId(), self::OTHER, [
            'list' => '/entity-lists/'.$closed->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame($this->list->getId(), $this->findEntity($mine->getId())->getList()->getId());

        // The list editors do
        $this->api('PUT', '/attribute-entities/'.$mine->getId(), self::USER, [
            'list' => '/entity-lists/'.$closed->getId(),
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame($closed->getId(), $this->findEntity($mine->getId())->getList()->getId());
    }

    public function testAnEntityCannotBeMovedToAListOfAnotherWorkspace(): void
    {
        $this->setUpScene();
        $entity = $this->createEntity($this->list, 'Red');
        $foreignWorkspace = $this->createOtherWorkspace(['ownerId' => self::USER]);
        $foreignList = $this->createEntityList(['name' => 'Foreign', 'workspace' => $foreignWorkspace]);

        $this->api('PUT', '/attribute-entities/'.$entity->getId(), self::USER, [
            'list' => '/entity-lists/'.$foreignList->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($this->list->getId(), $this->findEntity($entity->getId())->getList()->getId());
    }

    public function testMergeRequiresTheEditPermission(): void
    {
        $this->setUpScene(['allowNewValues' => true]);
        $main = $this->createEntity($this->list, 'Main');
        $other = $this->createEntity($this->list, 'Other');
        $pending = $this->createEntity($this->list, 'Pending', ['status' => AttributeEntity::STATUS_PENDING, 'creatorId' => self::OTHER]);

        $this->api('PUT', '/attribute-entities/'.$main->getId().'/merge', null, ['ids' => [$other->getId()]]);
        $this->assertResponseStatusCodeSame(401);

        $this->api('PUT', '/attribute-entities/'.$main->getId().'/merge', self::OTHER, ['ids' => [$other->getId()]]);
        $this->assertResponseStatusCodeSame(403);

        // A creator may edit its pending proposal, but not delete the approved values merged into it
        $this->api('PUT', '/attribute-entities/'.$pending->getId().'/merge', self::OTHER, ['ids' => [$other->getId()]]);
        $this->assertResponseStatusCodeSame(403);

        $this->assertNotNull($this->findEntity($other->getId()));
    }

    public function testMergeValidation(): void
    {
        $this->setUpScene();
        $main = $this->createEntity($this->list, 'Main');

        $this->api('PUT', '/attribute-entities/'.$main->getId().'/merge', self::USER, ['ids' => []]);
        $this->assertResponseStatusCodeSame(422);

        $this->api('PUT', '/attribute-entities/'.$main->getId().'/merge', self::USER, []);
        $this->assertResponseStatusCodeSame(422);

        $this->api('PUT', '/attribute-entities/9b2a7f5e-0000-4000-8000-000000000000/merge', self::USER, ['ids' => [$main->getId()]]);
        $this->assertResponseStatusCodeSame(404);
    }
}
