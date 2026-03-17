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
use App\Security\Voter\WorkspaceVoter;
use App\Tests\AbstractDataboxTestCase;
use App\Tests\Permission\Model\AssetPermissions;
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
        $this->assertEquals($expected->edit, $security->isGranted(AssetVoter::EDIT, $asset), $userMessage(sprintf('edit asset "%s" (editAsset)', $asset->getTitle())));
        $this->assertEquals($expected->editAttributes, $security->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset), $userMessage(sprintf('edit attributes of asset "%s" (editAttributes)', $asset->getTitle())));
        $this->assertEquals($expected->editTags, $security->isGranted(AssetVoter::EDIT_TAGS, $asset), $userMessage(sprintf('edit tags of asset "%s" (editAsset)', $asset->getTitle())));
        $this->assertEquals($expected->editPrivacy, $security->isGranted(AssetVoter::EDIT_PRIVACY, $asset), $userMessage(sprintf('edit privacy of asset "%s" (editPrivacy)', $asset->getTitle())));
        $this->assertEquals($expected->delete, $security->isGranted(AssetVoter::DELETE, $asset), $userMessage(sprintf('delete asset "%s" (editAsset)', $asset->getTitle())));
    }

    /**
     * @return \Generator|PermissionsTestCase[]
     */
    private function getCases(): \Generator
    {
        $fullAssetPerm = new AssetPermissions(
            edit: true,
            editAttributes: true,
            editTags: true,
            editPrivacy: true,
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

        $assetAllButTagsAndPrivacy = new AssetPermissions(
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
            'assetInARoot' => $assetAllButTagsAndPrivacy, // TODO  // TODO can I edit all assets in a collection just because I own its parent collection?
            'assetInBRoot' => $assetAllButTagsAndPrivacy, // TODO  // TODO can I edit all assets in a collection just because I own its parent collection?
            'assetLostAlice' => $assetAllButTagsAndPrivacy,
            'assetLostBob' => $assetAllButTagsAndPrivacy,
            'assetInAAlice' => $assetAllButTagsAndPrivacy,
            'assetInBAlice' => $assetAllButTagsAndPrivacy,
            'assetInABob' => $assetAllButTagsAndPrivacy,
            'assetInBBob' => $assetAllButTagsAndPrivacy,
        ];
        yield new PermissionsTestCase(
            'alice-out-of-workspace',
            self::ALICE,
            inWorkspace: false,
            canViewRoot: false,
        );
        yield new PermissionsTestCase(
            'alice-in-workspace',
            self::ALICE,
            ...$aliceCommon,
        );
        yield new PermissionsTestCase(
            'alice-ws-edit',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::EDIT,
            ],
            canEditRoot: true,
        );
        yield new PermissionsTestCase(
            'alice-ws-delete',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::DELETE,
            ],
            canDeleteRoot: true,
        );
        yield new PermissionsTestCase(
            'alice-ws-create',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::CREATE,
            ],
            canCreateCollectionInRoot: true,
        );
        yield new PermissionsTestCase(
            'alice-ws-owner',
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
            'alice-ws-child-create',
            self::ALICE,
            ...$aliceCommon,
            root: [
                PermissionInterface::CHILD_CREATE,
            ],
            canCreateAssetInRoot: true,
        );
    }
}
