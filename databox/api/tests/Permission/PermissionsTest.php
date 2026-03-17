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
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use App\Security\Voter\WorkspaceVoter;
use App\Tests\AbstractDataboxTestCase;
use App\Tests\Permission\Model\PermissionsTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PermissionsTest extends AbstractDataboxTestCase
{
    private const string ROOT = 'root';
    private const string ALICE = 'alice';
    private const string BOB = 'bob';
    private const string CAROL = 'carol';

    public function testPermissions(): void
    {
        $em = self::getEntityManager();
        $permissionManager = self::getService(PermissionManager::class);
        $security = self::getService(Security::class);
        $tokenStorage = self::getService(TokenStorageInterface::class);

        $workspace = new Workspace();
        $workspace->setOwnerId(self::ROOT);
        $workspace->setName('Sandbox');
        $workspace->setSlug('sandbox');
        $em->persist($workspace);

        $collectionA = new Collection();
        $collectionA->setOwnerId(self::ALICE);
        $collectionA->setTitle('A');
        $collectionA->setWorkspace($workspace);
        $em->persist($collectionA);

        $collectionB = new Collection();
        $collectionB->setOwnerId(self::BOB);
        $collectionB->setTitle('B');
        $collectionB->setWorkspace($workspace);
        $collectionB->setParent($collectionA);
        $em->persist($collectionB);

        $lostRoot = new Asset();
        $lostRoot->setWorkspace($workspace);
        $lostRoot->setTitle('Lost-root');
        $lostRoot->setOwnerId(self::ROOT);
        $em->persist($lostRoot);

        $lostAlice = new Asset();
        $lostAlice->setWorkspace($workspace);
        $lostAlice->setTitle('Lost-alice');
        $lostAlice->setOwnerId(self::ALICE);
        $em->persist($lostAlice);

        $lostBob = new Asset();
        $lostBob->setWorkspace($workspace);
        $lostBob->setTitle('Lost-bob');
        $lostBob->setOwnerId(self::ALICE);
        $em->persist($lostBob);

        $inARoot = new Asset();
        $inARoot->setReferenceCollection($collectionA);
        $inARoot->setWorkspace($workspace);
        $inARoot->setTitle('InA-root');
        $inARoot->setOwnerId(self::ROOT);
        $em->persist($inARoot);

        $inAAlice = new Asset();
        $inAAlice->setReferenceCollection($collectionA);
        $inAAlice->setWorkspace($workspace);
        $inAAlice->setTitle('InA-alice');
        $inAAlice->setOwnerId(self::ALICE);
        $em->persist($inAAlice);

        $inABob = new Asset();
        $inABob->setReferenceCollection($collectionA);
        $inABob->setWorkspace($workspace);
        $inABob->setTitle('InA-bob');
        $inABob->setOwnerId(self::BOB);
        $em->persist($inABob);

        $inBRoot = new Asset();
        $inBRoot->setReferenceCollection($collectionB);
        $inBRoot->setWorkspace($workspace);
        $inBRoot->setTitle('InB-root');
        $inBRoot->setOwnerId(self::ROOT);
        $em->persist($inBRoot);

        $inBAlice = new Asset();
        $inBAlice->setReferenceCollection($collectionB);
        $inBAlice->setWorkspace($workspace);
        $inBAlice->setTitle('InB-alice');
        $inBAlice->setOwnerId(self::ALICE);
        $em->persist($inBAlice);

        $inBBob = new Asset();
        $inBBob->setReferenceCollection($collectionB);
        $inBBob->setWorkspace($workspace);
        $inBBob->setTitle('InB-bob');
        $inBBob->setOwnerId(self::BOB);
        $em->persist($inBBob);

        $em->flush();

        $newAssetInRoot = new Asset();
        $newAssetInRoot->setWorkspace($workspace);

        $newAssetInA = new Asset();
        $newAssetInA->setReferenceCollection($collectionA);
        $newAssetInA->setWorkspace($workspace);

        $newAssetInB = new Asset();
        $newAssetInB->setReferenceCollection($collectionB);
        $newAssetInB->setWorkspace($workspace);

        $newCollectionInRoot = new Collection();
        $newCollectionInRoot->setWorkspace($workspace);

        $newCollectionInA = new Collection();
        $newCollectionInA->setWorkspace($workspace);
        $newCollectionInA->setParent($collectionA);

        $newCollectionInB = new Collection();
        $newCollectionInB->setWorkspace($workspace);
        $newCollectionInB->setParent($collectionB);

        $cases = $this->getCases();

        foreach ($cases as $caseNo => $case) {
            // Prepare context
            $em->createQueryBuilder()
                ->delete()
                ->from(AccessControlEntry::class, 't')
                ->getQuery()
                ->execute();

            $userId = $case->username;

            $addPerm = function (Asset|Collection|Workspace $object, array $permissions) use (
                $permissionManager,
                $userId
            ): void {
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

            if ($case->inWorkspace) {
                $case->root[] = PermissionInterface::VIEW;
            }

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

            $userMessage = fn (string $capacity): string => sprintf('[%d] User "%s" can %s', $caseNo, $case->username, $capacity);

            // Assertions
            $this->assertFalse($security->isGranted(WorkspaceVoter::CREATE, $workspace), $userMessage('create a new Workspace'));
            $this->assertEquals($case->canViewRoot, $security->isGranted(WorkspaceVoter::READ, $workspace), $userMessage('view root'));
            $this->assertEquals($case->canEditRoot, $security->isGranted(WorkspaceVoter::EDIT, $workspace), $userMessage('edit root'));
            $this->assertEquals($case->canDeleteRoot, $security->isGranted(WorkspaceVoter::DELETE, $workspace), $userMessage('delete root'));
            $this->assertEquals($case->canCreateCollectionInRoot, $security->isGranted(CollectionVoter::CREATE, $newCollectionInRoot), $userMessage('create collection in root'));
            $this->assertEquals($case->canCreateAssetInRoot, $security->isGranted(AssetVoter::CREATE, $newAssetInRoot), $userMessage('create asset in root'));
            $this->assertEquals($case->canEditAssetsInRoot, $security->isGranted(AssetVoter::EDIT, $lostRoot), $userMessage('edit all assets in root'));
            $this->assertEquals($case->canDeleteAssetsInRoot, $security->isGranted(AssetVoter::DELETE, $lostRoot), $userMessage('delete all assets in root'));

            $this->assertEquals($case->canViewA, $security->isGranted(CollectionVoter::READ, $collectionA), $userMessage('view collection A'));
            $this->assertEquals($case->canEditA, $security->isGranted(CollectionVoter::EDIT, $collectionA), $userMessage('edit collection A'));
            $this->assertEquals($case->canDeleteA, $security->isGranted(CollectionVoter::DELETE, $collectionA), $userMessage('delete collection A'));
            $this->assertEquals($case->canCreateCollectionUnderA, $security->isGranted(CollectionVoter::CREATE, $newCollectionInA), $userMessage('create collection under A'));
            $this->assertEquals($case->canCreateAssetInA, $security->isGranted(AssetVoter::CREATE, $newAssetInA), $userMessage('create asset in A'));
            $this->assertEquals($case->canEditAssetsInA, $security->isGranted(AssetVoter::EDIT, $inARoot), $userMessage('edit all assets in A'));
            $this->assertEquals($case->canDeleteAssetsInA, $security->isGranted(AssetVoter::DELETE, $inARoot), $userMessage('delete all assets in A'));

            $this->assertEquals($case->canViewB, $security->isGranted(CollectionVoter::READ, $collectionB), $userMessage('view collection B'));
            $this->assertEquals($case->canEditB, $security->isGranted(CollectionVoter::EDIT, $collectionB), $userMessage('edit collection B'));
            $this->assertEquals($case->canDeleteB, $security->isGranted(CollectionVoter::DELETE, $collectionB), $userMessage('delete collection B'));
            $this->assertEquals($case->canCreateCollectionUnderB, $security->isGranted(CollectionVoter::CREATE, $newCollectionInB), $userMessage('create collection under B'));
            $this->assertEquals($case->canCreateAssetInB, $security->isGranted(AssetVoter::CREATE, $newAssetInB), $userMessage('create asset in B'));
            $this->assertEquals($case->canEditAssetsInB, $security->isGranted(AssetVoter::EDIT, $inBRoot), $userMessage('edit all assets in B'));
            $this->assertEquals($case->canDeleteAssetsInB, $security->isGranted(AssetVoter::DELETE, $inBRoot), $userMessage('delete all assets in B'));
        }
    }

    /**
     * @return PermissionsTestCase[]
     */
    private function getCases(): array
    {
        return [
            0 => new PermissionsTestCase(
                self::ROOT,
                canViewRoot: true,
                canEditRoot: true,
                canDeleteRoot: true,
                canCreateCollectionInRoot: true,
                canCreateAssetInRoot: true,
                canEditAssetsInRoot: true,
                canDeleteAssetsInRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canEditAssetsInA: true,
                canDeleteAssetsInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInB: true,
                canDeleteAssetsInB: true,
            ),
            1 => new PermissionsTestCase(
                self::ALICE,
                inWorkspace: false,
                canViewRoot: false,
            ),
            2 => new PermissionsTestCase(
                self::ALICE,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,

            ),
            3 => new PermissionsTestCase(
                self::ALICE,
                root: [
                    PermissionInterface::EDIT,
                ],
                canEditRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,
                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,
            ),
            4 => new PermissionsTestCase(
                self::ALICE,
                root: [
                    PermissionInterface::EDIT,
                ],
                canEditRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,

                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,
            ),
            5 => new PermissionsTestCase(
                self::ALICE,
                root: [
                    PermissionInterface::DELETE,
                ],
                canDeleteRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,

                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,
            ),
            6 => new PermissionsTestCase(
                self::ALICE,
                root: [
                    PermissionInterface::CREATE,
                ],
                canCreateCollectionInRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,

                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,
            ),
            7 => new PermissionsTestCase(
                self::ALICE,
                root: [
                    PermissionInterface::OWNER,
                ],
                canEditRoot: true,
                canCreateCollectionInRoot: true,
                canCreateAssetInRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,

                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,
            ),
            8 => new PermissionsTestCase(
                self::ALICE,
                root: [
                    PermissionInterface::CHILD_CREATE,
                ],
                canCreateAssetInRoot: true,
                canViewA: true,
                canEditA: true,
                canDeleteA: true,
                canCreateCollectionUnderA: true,
                canCreateAssetInA: true,
                canViewB: true,
                canEditB: true,
                canDeleteB: true,
                canCreateCollectionUnderB: true,
                canCreateAssetInB: true,
                canEditAssetsInA: true, // TODO ?
                canDeleteAssetsInA: true,

                canEditAssetsInB: true, // TODO ?
                canDeleteAssetsInB: true,
            ),
        ];
    }
}
