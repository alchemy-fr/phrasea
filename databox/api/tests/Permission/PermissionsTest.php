<?php

namespace App\Tests\Permission;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use App\Security\Voter\DataboxExtraPermissionInterface;
use App\Security\Voter\WorkspaceVoter;
use App\Tests\AbstractDataboxTestCase;
use App\Tests\Permission\Model\AssetPermissions;
use App\Tests\Permission\Model\PermissionsTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PermissionsTest extends AbstractDataboxTestCase
{
    private const string EXTRA_PERM_PREFIX = 'extra_';
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
        $temporaryCacheFactory = self::getService(TemporaryCacheFactory::class);

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
        $lostBob->setOwnerId(self::BOB);
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

        foreach ($cases as $case) {
            // Prepare context
            $em->createQueryBuilder()
                ->delete()
                ->from(AccessControlEntry::class, 't')
                ->getQuery()
                ->execute();

            $temporaryCacheFactory->reset();
            $permissionManager->resetCache();
            $em->flush();

            $userId = $case->username;

            $addPerm = function (Asset|Collection|Workspace $object, array $permissions) use (
                $permissionManager,
                $userId
            ): void {
                $mask = 0;
                $metadata = [];

                foreach ($permissions as $permission) {
                    if (is_string($permission) && str_starts_with($permission, self::EXTRA_PERM_PREFIX)) {
                        $metadata[] = (int) substr($permission, strlen(self::EXTRA_PERM_PREFIX));
                    } else {
                        $mask |= $permission;
                    }
                }

                $permissionManager->updateOrCreateAce(
                    AccessControlEntryInterface::TYPE_USER_VALUE,
                    $userId,
                    $object::OBJECT_TYPE,
                    $object->getId(),
                    $mask,
                    $metadata,
                );
            };

            if ($case->inWorkspace) {
                $case->root[] = PermissionInterface::VIEW;
            }

            $addPerm($workspace, $case->root);
            $addPerm($collectionA, $case->a);
            $addPerm($collectionB, $case->b);
            $addPerm($lostRoot, $case->lostRoot);
            $addPerm($lostAlice, $case->lostAlice);
            $addPerm($lostBob, $case->lostBob);
            $addPerm($inARoot, $case->inARoot);
            $addPerm($inAAlice, $case->inAAlice);
            $addPerm($inABob, $case->inABob);
            $addPerm($inBRoot, $case->inBRoot);
            $addPerm($inBAlice, $case->inBAlice);
            $addPerm($inBBob, $case->inBBob);

            $user = new JwtUser('JWT', $userId, $userId, ['ROLE_USER']);
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);

            $userMessage = fn (string $capacity): string => sprintf('[%s] User "%s" can %s', $case->name, $case->username, $capacity);

            // Assertions
            $this->assertFalse($security->isGranted(WorkspaceVoter::CREATE, $workspace), $userMessage('create a new Workspace'));
            $this->assertEquals($case->canViewRoot, $security->isGranted(WorkspaceVoter::READ, $workspace), $userMessage('view root (canViewRoot)'));
            $this->assertEquals($case->canEditRoot, $security->isGranted(WorkspaceVoter::EDIT, $workspace), $userMessage('edit root (canEditRoot)'));
            $this->assertEquals($case->canDeleteRoot, $security->isGranted(WorkspaceVoter::DELETE, $workspace), $userMessage('delete root (canDeleteRoot)'));
            $this->assertEquals($case->canCreateCollectionInRoot, $security->isGranted(CollectionVoter::CREATE, $newCollectionInRoot), $userMessage('create collection in root (canCreateCollectionInRoot)'));
            $this->assertEquals($case->canCreateAssetInRoot, $security->isGranted(AssetVoter::CREATE, $newAssetInRoot), $userMessage('create asset in root (canCreateAssetInRoot)'));

            $this->assertEquals($case->canViewA, $security->isGranted(CollectionVoter::READ, $collectionA), $userMessage('view collection A (canViewA)'));
            $this->assertEquals($case->canEditA, $security->isGranted(CollectionVoter::EDIT, $collectionA), $userMessage('edit collection A (canEditA)'));
            $this->assertEquals($case->canDeleteA, $security->isGranted(CollectionVoter::DELETE, $collectionA), $userMessage('delete collection A (canDeleteA)'));
            $this->assertEquals($case->canCreateCollectionUnderA, $security->isGranted(CollectionVoter::CREATE, $newCollectionInA), $userMessage('create collection under A (canCreateCollectionUnderA)'));
            $this->assertEquals($case->canCreateAssetInA, $security->isGranted(AssetVoter::CREATE, $newAssetInA), $userMessage('create asset in A (canCreateAssetInA)'));

            $this->assertEquals($case->canViewB, $security->isGranted(CollectionVoter::READ, $collectionB), $userMessage('view collection B (canViewB)'));
            $this->assertEquals($case->canEditB, $security->isGranted(CollectionVoter::EDIT, $collectionB), $userMessage('edit collection B (canEditB)'));
            $this->assertEquals($case->canDeleteB, $security->isGranted(CollectionVoter::DELETE, $collectionB), $userMessage('delete collection B (canDeleteB)'));
            $this->assertEquals($case->canCreateCollectionUnderB, $security->isGranted(CollectionVoter::CREATE, $newCollectionInB), $userMessage('create collection under B (canCreateCollectionUnderB)'));
            $this->assertEquals($case->canCreateAssetInB, $security->isGranted(AssetVoter::CREATE, $newAssetInB), $userMessage('create asset in B (canCreateAssetInB)'));

            $this->assertAssetPermissions($case->assetLostRoot, $lostRoot, $security, $userMessage);
            $this->assertAssetPermissions($case->assetLostAlice, $lostAlice, $security, $userMessage);
            $this->assertAssetPermissions($case->assetLostBob, $lostBob, $security, $userMessage);

            $this->assertAssetPermissions($case->assetInARoot, $inARoot, $security, $userMessage);
            $this->assertAssetPermissions($case->assetInAAlice, $inAAlice, $security, $userMessage);
            $this->assertAssetPermissions($case->assetInABob, $inABob, $security, $userMessage);

            $this->assertAssetPermissions($case->assetInBRoot, $inBRoot, $security, $userMessage);
            $this->assertAssetPermissions($case->assetInBAlice, $inBAlice, $security, $userMessage);
            $this->assertAssetPermissions($case->assetInBBob, $inBBob, $security, $userMessage);
        }
    }

    private function assertAssetPermissions(AssetPermissions $expected, Asset $asset, Security $security, callable $userMessage): void
    {
        $this->assertEquals($expected->view, $security->isGranted(AssetVoter::READ, $asset), $userMessage(sprintf('view asset "%s"', $asset->getTitle())));
        $this->assertEquals($expected->edit, $security->isGranted(AssetVoter::EDIT, $asset), $userMessage(sprintf('edit asset "%s"', $asset->getTitle())));
        $this->assertEquals($expected->editAttributes, $security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset), $userMessage(sprintf('edit attributes of asset "%s"', $asset->getTitle())));
        $this->assertEquals($expected->editPermissions, $security->isGranted(AssetVoter::EDIT_PERMISSIONS, $asset), $userMessage(sprintf('edit permissions of asset "%s"', $asset->getTitle())));
        $this->assertEquals($expected->delete, $security->isGranted(AssetVoter::DELETE, $asset), $userMessage(sprintf('delete asset "%s"', $asset->getTitle())));
    }

    /**
     * @return \Generator|PermissionsTestCase[]
     */
    private function getCases(): \Generator
    {
        $fullAssetPerm = new AssetPermissions(
            view: true,
            edit: true,
            editAttributes: true,
            editPermissions: true,
            delete: true,
        );

        yield new PermissionsTestCase(
            'root-0',
            self::ROOT,
            canViewRoot: true,
            canEditRoot: true,
            canDeleteRoot: true,
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
            assetLostRoot: $fullAssetPerm,
            assetLostAlice: $fullAssetPerm,
            assetLostBob: $fullAssetPerm,
            assetInARoot: $fullAssetPerm,
            assetInAAlice: $fullAssetPerm,
            assetInABob: $fullAssetPerm,
            assetInBRoot: $fullAssetPerm,
            assetInBAlice: $fullAssetPerm,
            assetInBBob: $fullAssetPerm,
        );

        $assetAllButPerm = new AssetPermissions(
            view: true,
            edit: true,
            editAttributes: true,
            delete: true,
        );

        $aliceCommon = [
            'canViewA' => true,
            'canEditA' => true,
            'canDeleteA' => true,
            'canCreateCollectionUnderA' => true,
            'canViewB' => true,
            'canEditB' => true,
            'canDeleteB' => true,
            'canCreateCollectionUnderB' => true,
            'assetInARoot' => $assetAllButPerm,
            'assetInBRoot' => $assetAllButPerm,
            'assetLostAlice' => $assetAllButPerm,
            'assetInAAlice' => $assetAllButPerm,
            'assetInBAlice' => $assetAllButPerm,
            'assetInABob' => $assetAllButPerm,
            'assetInBBob' => $assetAllButPerm,
        ];
        yield new PermissionsTestCase(
            'out-of-workspace',
            self::ALICE,
            inWorkspace: false,
            canViewRoot: false,
        );
        yield new PermissionsTestCase(
            'in-workspace',
            self::ALICE,
            ...$aliceCommon,
        );
        yield new PermissionsTestCase(
            'ws-edit',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::EDIT,
            ],
            canEditRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-delete',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::DELETE,
            ],
            canDeleteRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-create',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::CREATE,
            ],
            canCreateCollectionInRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-owner',
            self::ALICE,
            ...([
                'assetLostAlice' => $fullAssetPerm,
                'assetLostBob' => $fullAssetPerm,
                'assetInARoot' => $fullAssetPerm,
                'assetInAAlice' => $fullAssetPerm,
                'assetInABob' => $fullAssetPerm,
                'assetInBRoot' => $fullAssetPerm,
                'assetInBAlice' => $fullAssetPerm,
                'assetInBBob' => $fullAssetPerm,
            ] + $aliceCommon),
            root: [
                PermissionInterface::OWNER,
            ],
            canEditRoot: true,
            canCreateCollectionInRoot: true,
            canCreateAssetInRoot: true,
            canCreateAssetInA: true,
            canCreateAssetInB: true,
            assetLostRoot: $fullAssetPerm,
        );
        yield new PermissionsTestCase(
            'ws-child-create',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInRoot: true,
        );
        yield new PermissionsTestCase(
            'a-child-create',
            self::ALICE,
            ...$aliceCommon,
            a: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInA: true,
            canCreateAssetInB: true,
        );
        yield new PermissionsTestCase(
            'b-child-create',
            self::ALICE,
            ...$aliceCommon,
            b: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInB: true,
        );

        $bobCommon = [
            'canViewB' => true,
            'canEditB' => true,
            'canDeleteB' => true,
            'canCreateCollectionUnderB' => true,
            'assetInBRoot' => $assetAllButPerm,
            'assetLostBob' => $assetAllButPerm,
            'assetInABob' => $assetAllButPerm,
            'assetInBAlice' => $assetAllButPerm,
            'assetInBBob' => $assetAllButPerm,
        ];
        yield new PermissionsTestCase(
            'out-of-workspace',
            self::BOB,
            inWorkspace: false,
            canViewRoot: false,
        );
        yield new PermissionsTestCase(
            'in-workspace',
            self::BOB,
            ...$bobCommon,
        );
        yield new PermissionsTestCase(
            'ws-edit',
            self::BOB,
            ...$bobCommon,
            root: [
                PermissionInterface::EDIT,
            ],
            canEditRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-delete',
            self::BOB,
            ...$bobCommon,
            root: [
                PermissionInterface::DELETE,
            ],
            canDeleteRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-create',
            self::BOB,
            ...$bobCommon,
            root: [
                PermissionInterface::CREATE,
            ],
            canCreateCollectionInRoot: true,
            canCreateCollectionUnderA: true,
        );
        yield new PermissionsTestCase(
            'ws-owner',
            self::BOB,
            ...([
                'assetLostAlice' => $fullAssetPerm,
                'assetLostBob' => $fullAssetPerm,
                'assetInARoot' => $fullAssetPerm,
                'assetInAAlice' => $fullAssetPerm,
                'assetInABob' => $fullAssetPerm,
                'assetInBRoot' => $fullAssetPerm,
                'assetInBAlice' => $fullAssetPerm,
                'assetInBBob' => $fullAssetPerm,
            ] + $bobCommon),
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
            canCreateAssetInB: true,
            assetLostRoot: $fullAssetPerm,
        );
        yield new PermissionsTestCase(
            'ws-child-create',
            self::BOB,
            ...$bobCommon,
            root: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInRoot: true,
        );

        $carolCommon = [];
        yield new PermissionsTestCase(
            'out-of-workspace',
            self::CAROL,
            inWorkspace: false,
            canViewRoot: false,
        );
        yield new PermissionsTestCase(
            'in-workspace',
            self::CAROL,
            ...$carolCommon,
        );
        yield new PermissionsTestCase(
            'ws-edit',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::EDIT,
            ],
            canEditRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-delete',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::DELETE,
            ],
            canDeleteRoot: true,
        );
        yield new PermissionsTestCase(
            'ws-create',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CREATE,
            ],
            canCreateCollectionInRoot: true,
            canCreateCollectionUnderA: true,
            canCreateCollectionUnderB: true,
        );

        yield new PermissionsTestCase(
            'a-create',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CREATE,
            ],
            canCreateCollectionUnderA: true,
            canCreateCollectionUnderB: true,
        );

        yield new PermissionsTestCase(
            'ws-owner',
            self::CAROL,
            ...([
                'assetLostAlice' => $fullAssetPerm,
                'assetLostBob' => $fullAssetPerm,
                'assetInARoot' => $fullAssetPerm,
                'assetInAAlice' => $fullAssetPerm,
                'assetInABob' => $fullAssetPerm,
                'assetInBRoot' => $fullAssetPerm,
                'assetInBAlice' => $fullAssetPerm,
                'assetInBBob' => $fullAssetPerm,
            ] + $carolCommon),
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
            assetLostRoot: $fullAssetPerm,
        );
        yield new PermissionsTestCase(
            'ws-child-create',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInRoot: true,
        );
        yield new PermissionsTestCase(
            'coll-a-child-create',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInA: true,
            canCreateAssetInB: true,
        );
        yield new PermissionsTestCase(
            'coll-b-child-create',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInB: true,
        );

        $assetView = new AssetPermissions(
            view: true,
        );

        yield new PermissionsTestCase(
            'root-child-view',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_VIEW,
            ],
            assetLostRoot: $assetView,
            assetLostAlice: $assetView,
            assetLostBob: $assetView,
            assetInARoot: $assetView,
            assetInAAlice: $assetView,
            assetInABob: $assetView,
            assetInBRoot: $assetView,
            assetInBAlice: $assetView,
            assetInBBob: $assetView,
        );
        yield new PermissionsTestCase(
            'coll-a-child-view',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_VIEW,
            ],
            assetInARoot: $assetView,
            assetInAAlice: $assetView,
            assetInABob: $assetView,
            assetInBRoot: $assetView,
            assetInBAlice: $assetView,
            assetInBBob: $assetView,
        );
        yield new PermissionsTestCase(
            'coll-b-child-view',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_VIEW,
            ],
            assetInBRoot: $assetView,
            assetInBAlice: $assetView,
            assetInBBob: $assetView,
        );

        yield new PermissionsTestCase(
            'asset-lost-root-owner',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::OWNER,
            ],
            assetLostRoot: $assetAllButPerm,
        );
        yield new PermissionsTestCase(
            'asset-lost-alice-owner',
            self::CAROL,
            ...$carolCommon,
            lostAlice: [
                PermissionInterface::OWNER,
            ],
            assetLostAlice: $assetAllButPerm,
        );
        yield new PermissionsTestCase(
            'asset-lost-bob-owner',
            self::CAROL,
            ...$carolCommon,
            lostBob: [
                PermissionInterface::OWNER,
            ],
            assetLostBob: $assetAllButPerm,
        );

        yield new PermissionsTestCase(
            'asset-lost-root-view',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::VIEW,
            ],
            assetLostRoot: $assetView,
        );
        yield new PermissionsTestCase(
            'asset-lost-alice-view',
            self::CAROL,
            ...$carolCommon,
            lostAlice: [
                PermissionInterface::VIEW,
            ],
            assetLostAlice: $assetView,
        );
        yield new PermissionsTestCase(
            'asset-lost-bob-view',
            self::CAROL,
            ...$carolCommon,
            lostBob: [
                PermissionInterface::VIEW,
            ],
            assetLostBob: $assetView,
        );

        yield new PermissionsTestCase(
            'asset-inA-root-view',
            self::CAROL,
            ...$carolCommon,
            inARoot: [
                PermissionInterface::VIEW,
            ],
            assetInARoot: $assetView,
        );
        yield new PermissionsTestCase(
            'asset-inA-alice-view',
            self::CAROL,
            ...$carolCommon,
            inAAlice: [
                PermissionInterface::VIEW,
            ],
            assetInAAlice: $assetView,
        );
        yield new PermissionsTestCase(
            'asset-inA-bob-view',
            self::CAROL,
            ...$carolCommon,
            inABob: [
                PermissionInterface::VIEW,
            ],
            assetInABob: $assetView,
        );

        yield new PermissionsTestCase(
            'asset-inB-root-view',
            self::CAROL,
            ...$carolCommon,
            inBRoot: [
                PermissionInterface::VIEW,
            ],
            assetInBRoot: $assetView,
        );
        yield new PermissionsTestCase(
            'asset-inB-alice-view',
            self::CAROL,
            ...$carolCommon,
            inBAlice: [
                PermissionInterface::VIEW,
            ],
            assetInBAlice: $assetView,
        );
        yield new PermissionsTestCase(
            'asset-inB-bob-view',
            self::CAROL,
            ...$carolCommon,
            inBBob: [
                PermissionInterface::VIEW,
            ],
            assetInBBob: $assetView,
        );

        yield new PermissionsTestCase(
            'asset-lost-root-edit-attributes',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::EDIT,
            ],
            assetLostRoot: new AssetPermissions(
                editAttributes: true,
            ),
        );

        yield new PermissionsTestCase(
            'asset-lost-root-edit-permissions-with-ownership',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::OWNER,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetLostRoot: new AssetPermissions(
                view: true,
                edit: true,
                editAttributes: true,
                editPermissions: true,
                delete: true,
            ),
        );
        yield new PermissionsTestCase(
            'asset-lost-root-edit-permissions-with-operator',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::OPERATOR,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetLostRoot: new AssetPermissions(
                edit: true,
            ),
        );
        yield new PermissionsTestCase(
            'asset-lost-root-edit-permissions-with-edit',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::EDIT,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetLostRoot: new AssetPermissions(
                editAttributes: true,
            ),
        );
        yield new PermissionsTestCase(
            'asset-lost-root-edit-permissions-with-edit',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::OPERATOR,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetLostRoot: new AssetPermissions(
                edit: true,
            ),
        );
        yield new PermissionsTestCase(
            'asset-lost-root-edit-permissions-with-zero-mask',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetLostRoot: new AssetPermissions(),
        );

        yield new PermissionsTestCase(
            'asset-lost-root-edit',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::OPERATOR,
            ],
            assetLostRoot: new AssetPermissions(
                edit: true,
            ),
        );

        $assetDelete = new AssetPermissions(
            delete: true,
        );
        yield new PermissionsTestCase(
            'asset-lost-root-delete',
            self::CAROL,
            ...$carolCommon,
            lostRoot: [
                PermissionInterface::DELETE,
            ],
            assetLostRoot: $assetDelete,
        );

        yield new PermissionsTestCase(
            'root-child-delete',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_DELETE,
            ],
            assetLostRoot: $assetDelete,
            assetLostAlice: $assetDelete,
            assetLostBob: $assetDelete,
            assetInARoot: $assetDelete,
            assetInAAlice: $assetDelete,
            assetInABob: $assetDelete,
            assetInBRoot: $assetDelete,
            assetInBAlice: $assetDelete,
            assetInBBob: $assetDelete,
        );

        $assetEditAttributes = new AssetPermissions(
            editAttributes: true,
        );
        yield new PermissionsTestCase(
            'root-child-edit',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_EDIT,
            ],
            assetLostRoot: $assetEditAttributes,
            assetLostAlice: $assetEditAttributes,
            assetLostBob: $assetEditAttributes,
            assetInARoot: $assetEditAttributes,
            assetInAAlice: $assetEditAttributes,
            assetInABob: $assetEditAttributes,
            assetInBRoot: $assetEditAttributes,
            assetInBAlice: $assetEditAttributes,
            assetInBBob: $assetEditAttributes,
        );

        $assetEdit = new AssetPermissions(
            edit: true,
        );
        yield new PermissionsTestCase(
            'root-child-operator',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_OPERATOR,
            ],
            assetLostRoot: $assetEdit,
            assetLostAlice: $assetEdit,
            assetLostBob: $assetEdit,
            assetInARoot: $assetEdit,
            assetInAAlice: $assetEdit,
            assetInABob: $assetEdit,
            assetInBRoot: $assetEdit,
            assetInBAlice: $assetEdit,
            assetInBBob: $assetEdit,
        );

        $assetOwnerButPerms = new AssetPermissions(
            view: true,
            edit: true,
            editAttributes: true,
            delete: true,
        );
        yield new PermissionsTestCase(
            'root-child-owner',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_OWNER,
            ],
            assetLostRoot: $assetOwnerButPerms,
            assetLostAlice: $assetOwnerButPerms,
            assetLostBob: $assetOwnerButPerms,
            assetInARoot: $assetOwnerButPerms,
            assetInAAlice: $assetOwnerButPerms,
            assetInABob: $assetOwnerButPerms,
            assetInBRoot: $assetOwnerButPerms,
            assetInBAlice: $assetOwnerButPerms,
            assetInBBob: $assetOwnerButPerms,
        );

        yield new PermissionsTestCase(
            'a-child-delete',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_DELETE,
            ],
            assetInARoot: $assetDelete,
            assetInAAlice: $assetDelete,
            assetInABob: $assetDelete,
            assetInBRoot: $assetDelete,
            assetInBAlice: $assetDelete,
            assetInBBob: $assetDelete,
        );

        $assetEditAttributes = new AssetPermissions(
            editAttributes: true,
        );
        yield new PermissionsTestCase(
            'a-child-edit',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_EDIT,
            ],
            assetInARoot: $assetEditAttributes,
            assetInAAlice: $assetEditAttributes,
            assetInABob: $assetEditAttributes,
            assetInBRoot: $assetEditAttributes,
            assetInBAlice: $assetEditAttributes,
            assetInBBob: $assetEditAttributes,
        );

        yield new PermissionsTestCase(
            'a-child-operator',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_OPERATOR,
            ],
            assetInARoot: $assetEdit,
            assetInAAlice: $assetEdit,
            assetInABob: $assetEdit,
            assetInBRoot: $assetEdit,
            assetInBAlice: $assetEdit,
            assetInBBob: $assetEdit,
        );

        yield new PermissionsTestCase(
            'a-child-owner',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_OWNER,
            ],
            assetInARoot: $assetOwnerButPerms,
            assetInAAlice: $assetOwnerButPerms,
            assetInABob: $assetOwnerButPerms,
            assetInBRoot: $assetOwnerButPerms,
            assetInBAlice: $assetOwnerButPerms,
            assetInBBob: $assetOwnerButPerms,
        );

        yield new PermissionsTestCase(
            'b-child-delete',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_DELETE,
            ],
            assetInBRoot: $assetDelete,
            assetInBAlice: $assetDelete,
            assetInBBob: $assetDelete,
        );

        $assetEditAttributes = new AssetPermissions(
            editAttributes: true,
        );
        yield new PermissionsTestCase(
            'b-child-edit',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_EDIT,
            ],
            assetInBRoot: $assetEditAttributes,
            assetInBAlice: $assetEditAttributes,
            assetInBBob: $assetEditAttributes,
        );

        yield new PermissionsTestCase(
            'b-child-operator',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_OPERATOR,
            ],
            assetInBRoot: $assetEdit,
            assetInBAlice: $assetEdit,
            assetInBBob: $assetEdit,
        );

        yield new PermissionsTestCase(
            'b-child-owner',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_OWNER,
            ],
            assetInBRoot: $assetOwnerButPerms,
            assetInBAlice: $assetOwnerButPerms,
            assetInBBob: $assetOwnerButPerms,
        );

        yield new PermissionsTestCase(
            'a-child-owner-and-permissions',
            self::CAROL,
            ...$carolCommon,
            a: [
                PermissionInterface::CHILD_OWNER,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetInARoot: $fullAssetPerm,
            assetInAAlice: $fullAssetPerm,
            assetInABob: $fullAssetPerm,
            assetInBRoot: $fullAssetPerm,
            assetInBAlice: $fullAssetPerm,
            assetInBBob: $fullAssetPerm,
        );

        yield new PermissionsTestCase(
            'b-child-owner-and-permissions',
            self::CAROL,
            ...$carolCommon,
            b: [
                PermissionInterface::CHILD_OWNER,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetInBRoot: $fullAssetPerm,
            assetInBAlice: $fullAssetPerm,
            assetInBBob: $fullAssetPerm,
        );

        yield new PermissionsTestCase(
            'ws-child-owner-and-permissions',
            self::CAROL,
            ...$carolCommon,
            root: [
                PermissionInterface::CHILD_OWNER,
                self::EXTRA_PERM_PREFIX.DataboxExtraPermissionInterface::PERM_EDIT_PERMISSIONS,
            ],
            assetLostRoot: $fullAssetPerm,
            assetLostAlice: $fullAssetPerm,
            assetLostBob: $fullAssetPerm,
            assetInARoot: $fullAssetPerm,
            assetInAAlice: $fullAssetPerm,
            assetInABob: $fullAssetPerm,
            assetInBRoot: $fullAssetPerm,
            assetInBAlice: $fullAssetPerm,
            assetInBBob: $fullAssetPerm,
        );
    }
}
