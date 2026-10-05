<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * PUT /collections/{id}/move/{dest}.
 */
final class CollectionMoveTest extends AbstractDataboxTestCase
{
    use CollectionTestTrait;

    public function testMoveUnderAnotherCollection(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A']);
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'B']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $a]);
        $grandChild = $this->createCollection(['workspace' => $workspace, 'name' => 'GrandChild', 'parent' => $child]);

        $response = $this->request('PUT', '/collections/'.$child->getId().'/move/'.$b->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertSame('', $response->getContent());

        $this->assertSame($b->getId(), $this->findCollection($child->getId())->getParent()->getId());

        // The whole branch is relocated
        $response = $this->request('GET', '/collections/'.$grandChild->getId(), self::USER);
        $this->assertJsonContains([
            'absolutePath' => '/'.$b->getId().'/'.$child->getId().'/'.$grandChild->getId(),
            'absoluteName' => 'B'.self::SEP.'Child'.self::SEP.'GrandChild',
        ]);
        $this->assertSame($child->getId(), $response->toArray()['parentId']);
    }

    public function testMoveToRoot(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $a]);

        $this->request('PUT', '/collections/'.$child->getId().'/move/root', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findCollection($child->getId())->getParent());

        $response = $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $data = $response->toArray();
        $this->assertSame('/'.$child->getId(), $data['absolutePath']);
        $this->assertSame('Child', $data['absoluteName']);
        $this->assertNull($data['parentId'] ?? null);
        $this->assertNull($data['inheritedPrivacy'] ?? null);
    }

    public function testMoveChangesInheritedPrivacy(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::OTHER, 'members' => [self::USER]]);
        $public = $this->createCollection(['workspace' => $workspace, 'name' => 'Public']);
        $this->setCollectionPrivacy($public, Privacy::PUBLIC_IN_WORKSPACE);
        $secret = $this->createCollection(['workspace' => $workspace, 'name' => 'Secret']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $public]);

        $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $this->assertResponseStatusCodeSame(200);

        $this->request('PUT', '/collections/'.$child->getId().'/move/'.$secret->getId(), self::OTHER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);

        // The child no longer inherits the readable privacy
        $this->request('GET', '/collections/'.$child->getId(), self::USER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/collections/'.$child->getId(), self::OTHER);
        $this->assertJsonContains(['inheritedPrivacy' => Privacy::SECRET]);
    }

    public function testMoveToAnotherWorkspaceIsRejected(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $otherWorkspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);
        $destination = $this->createCollection(['workspace' => $otherWorkspace, 'name' => 'Elsewhere']);

        $this->request('PUT', '/collections/'.$collection->getId().'/move/'.$destination->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['hydra:description' => 'Cannot add a sub-collection in a different workspace']);
        $this->assertNull($this->findCollection($collection->getId())->getParent());
    }

    public function testMoveIntoItselfIsRejected(): void
    {
        $this->markTestIncomplete('BUG: MoveCollectionProcessor (src/Api/Processor/MoveCollectionProcessor.php:49) throws a plain \InvalidArgumentException, which gives a 500 instead of a 400');

        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);

        $this->request('PUT', '/collections/'.$collection->getId().'/move/'.$collection->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->findCollection($collection->getId())->getParent());
    }

    public function testMoveIntoOwnDescendantIsRejected(): void
    {
        $this->markTestIncomplete('BUG: MoveCollectionProcessor (src/Api/Processor/MoveCollectionProcessor.php:48) only rejects the collection itself: moving a collection under one of its descendants is accepted and creates a parent cycle, then IndexCollectionBranchHandler::handleChildren() (src/Consumer/Handler/Search/IndexCollectionBranchHandler.php:39) recurses forever (the PHP process crashes, do not run this test without the fix)');

        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $a = $this->createCollection(['workspace' => $workspace, 'name' => 'A']);
        $b = $this->createCollection(['workspace' => $workspace, 'name' => 'B', 'parent' => $a]);
        $c = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'parent' => $b]);

        $this->request('PUT', '/collections/'.$a->getId().'/move/'.$c->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->findCollection($a->getId())->getParent());
    }

    public function testMoveToUnknownDestination(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);

        $this->request('PUT', '/collections/'.$collection->getId().'/move/00000000-0000-4000-8000-000000000000', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMoveUnknownCollection(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $destination = $this->createCollection(['workspace' => $workspace, 'name' => 'D']);

        $this->request('PUT', '/collections/00000000-0000-4000-8000-000000000000/move/'.$destination->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMoveRequiresEditOnSource(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C']);
        $destination = $this->createCollection(['workspace' => $workspace, 'name' => 'D', 'ownerId' => self::USER]);
        $this->grantUserOnObject(self::USER, $collection, PermissionInterface::VIEW);

        $this->request('PUT', '/collections/'.$collection->getId().'/move/'.$destination->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->request('PUT', '/collections/'.$collection->getId().'/move/root', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findCollection($collection->getId())->getParent());

        $this->request('PUT', '/collections/'.$collection->getId().'/move/'.$destination->getId(), self::ANONYMOUS, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testMoveRequiresEditOnDestination(): void
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'ownerId' => self::USER]);
        $destination = $this->createCollection(['workspace' => $workspace, 'name' => 'D']);
        $this->setCollectionPrivacy($destination, Privacy::PUBLIC_IN_WORKSPACE);

        $this->request('PUT', '/collections/'.$collection->getId().'/move/'.$destination->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findCollection($collection->getId())->getParent());

        $this->grantUserOnObject(self::USER, $destination, PermissionInterface::EDIT);
        $this->request('PUT', '/collections/'.$collection->getId().'/move/'.$destination->getId(), self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertSame($destination->getId(), $this->findCollection($collection->getId())->getParent()->getId());
    }

    public function testMoveOutOfBranchWithEditInheritedFromParent(): void
    {
        // EDIT on a parent is inherited by the children; so is the right to move them
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'P']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'parent' => $parent]);
        $this->grantUserOnObject(self::USER, $parent, PermissionInterface::VIEW | PermissionInterface::EDIT);

        $this->request('PUT', '/collections/'.$child->getId().'/move/root', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->findCollection($child->getId())->getParent());
    }
}
