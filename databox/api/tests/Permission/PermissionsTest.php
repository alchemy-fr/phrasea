<?php

namespace App\Tests\Permission;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use App\Tests\AbstractDataboxTestCase;
use App\Tests\Permission\Model\PermissionsTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PermissionsTest extends AbstractDataboxTestCase
{
    public function testPermissions(): void
    {
        $em = self::getEntityManager();
        $permissionManager = self::getService(PermissionManager::class);
        $security = self::getService(Security::class);
        $tokenStorage = self::getService(TokenStorageInterface::class);

        $root = 'root';
        $alice = 'alice';
        $bob = 'bob';
        $carol = 'carol';

        $workspace = new Workspace();
        $workspace->setOwnerId($root);
        $workspace->setName('Sandbox');
        $workspace->setSlug('sandbox');
        $em->persist($workspace);

        $collectionA = new Collection();
        $collectionA->setOwnerId($alice);
        $collectionA->setTitle('A');
        $collectionA->setWorkspace($workspace);
        $em->persist($collectionA);

        $collectionB = new Collection();
        $collectionB->setOwnerId($bob);
        $collectionB->setTitle('B');
        $collectionB->setWorkspace($workspace);
        $collectionB->setParent($collectionA);
        $em->persist($collectionB);

        $lostAlice = new Asset();
        $lostAlice->setWorkspace($workspace);
        $lostAlice->setTitle('Lost-alice');
        $lostAlice->setOwnerId($alice);
        $em->persist($lostAlice);

        $lostBob = new Asset();
        $lostBob->setWorkspace($workspace);
        $lostBob->setTitle('Lost-bob');
        $lostBob->setOwnerId($alice);
        $em->persist($lostBob);

        $inAAlice = new Asset();
        $inAAlice->setReferenceCollection($collectionA);
        $inAAlice->setWorkspace($workspace);
        $inAAlice->setTitle('InA-alice');
        $inAAlice->setOwnerId($alice);
        $em->persist($inAAlice);

        $inABob = new Asset();
        $inABob->setReferenceCollection($collectionA);
        $inABob->setWorkspace($workspace);
        $inABob->setTitle('InA-bob');
        $inABob->setOwnerId($bob);
        $em->persist($inABob);

        $inBAlice = new Asset();
        $inBAlice->setReferenceCollection($collectionB);
        $inBAlice->setWorkspace($workspace);
        $inBAlice->setTitle('InB-alice');
        $inBAlice->setOwnerId($alice);
        $em->persist($inBAlice);

        $inBBob = new Asset();
        $inBBob->setReferenceCollection($collectionB);
        $inBBob->setWorkspace($workspace);
        $inBBob->setTitle('InB-bob');
        $inBBob->setOwnerId($bob);
        $em->persist($inBBob);

        $em->flush();

        $cases = $this->getCases();

        foreach ($cases as $case) {
            // Prepare context
            $em->createQueryBuilder()
                ->delete()
                ->from(AccessControlEntry::class, 't')
                ->getQuery()
                ->execute();

            $userId = $case->username;

            $addPerm = function (Asset|Collection|Workspace $object, array $permissions) use ($permissionManager, $userId): void {
                $mask = 0;

                foreach ($permissions as $permission) {
                    $mask |= $permission;
                }

                $permissionManager->updateOrCreateAce(
                    AccessControlEntryInterface::TYPE_USER_VALUE,
                    $userId,
                    $object::OBJECT_TYPE,
                    $object->getId(),
                    $mask
                );
            };

            $addPerm($workspace, $case->root);
            $addPerm($collectionA, $case->a);
            $addPerm($collectionB, $case->b);
            $addPerm($inAAlice, $case->inAAlice);
            $addPerm($inABob, $case->inABob);
            $addPerm($inBAlice, $case->inBAlice);
            $addPerm($inBBob, $case->inBBob);

            $user = new JwtUser('JWT', $userId, $userId, ['ROLE_USER']);
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);

            // Assertions
            $this->assertFalse($security->isGranted(WorkspaceVoter::CREATE, $workspace), sprintf('User "%s" can create a new Workspace', $case->username));
            $this->assertEquals($case->canViewRoot, $security->isGranted(WorkspaceVoter::READ, $workspace), sprintf('User "%s" can view root', $case->username));
            $this->assertEquals($case->canCreateCollectionInRoot, $security->isGranted(WorkspaceVoter::CREATE_COLLECTION, $workspace), sprintf('User "%s" can create collection in root', $case->username));
            $this->assertEquals($case->canCreateAssetInRoot, $security->isGranted(WorkspaceVoter::CREATE_ASSET, $workspace), sprintf('User "%s" can create asset in root', $case->username));
        }
    }

    /**
     * @return PermissionsTestCase[]
     */
    private function getCases(): array
    {
        return [
            new PermissionsTestCase(
                'root',
                canViewRoot: true,
                canCreateCollectionInRoot: true,
            ),
            new PermissionsTestCase(
                'alice',
                canViewRoot: false,
            ),
            new PermissionsTestCase(
                'alice',
                root: [
                    PermissionInterface::VIEW,
                ]
            ),
            new PermissionsTestCase(
                'alice',
                root: [
                    PermissionInterface::VIEW,
                    PermissionInterface::CREATE,
                ]
            ),
        ];
    }
}
