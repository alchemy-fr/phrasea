<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\DataboxExtraPermissionInterface;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Quarantine resolution: POST /assets/{id}/quarantine-bypass,
 * GET /assets/{id}/duplicates and POST /assets/{id}/add-as-version.
 *
 * The workspace is owned by OTHER_USER_UID (who may bypass the quarantine);
 * USER_UID is a member owning the quarantined asset.
 */
final class AssetQuarantineTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    private const string CHECKSUM = 'a3f5c2e1d4b6a798a3f5c2e1d4b6a798a3f5c2e1d4b6a798a3f5c2e1d4b6a798';

    public function testWorkspaceOwnerBypassesTheQuarantine(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);

        $data = $this->request('POST', '/assets/'.$quarantined->getId().'/quarantine-bypass', self::OTHER)->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame(AssetStatusEnum::Accepted->value, $data['status']);
        $this->assertSame(AssetStatusEnum::Accepted, $this->reloadAsset($quarantined->getId())->getStatus());

        $file = self::getEntityManager()->find(File::class, $file->getId());
        $this->assertSame(File::ANALYSIS_BYPASSED, $file->getAnalysis()->toArray()['status']);
        $this->assertTrue($file->isAccepted());
    }

    public function testBypassIsAWorkspacePermission(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined] = $this->createQuarantinedAsset($workspace);

        // The owner of the asset sees it, but cannot accept it
        $this->request('POST', '/assets/'.$quarantined->getId().'/quarantine-bypass', self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/assets/'.$quarantined->getId().'/quarantine-bypass', null);
        $this->assertResponseStatusCodeSame(401);

        $this->assertSame(AssetStatusEnum::Quarantined, $this->reloadAsset($quarantined->getId())->getStatus());
    }

    public function testBypassGrantedThroughWorkspaceMetadata(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        $quarantined = $this->createQuarantinedAsset($workspace, ['ownerId' => self::OTHER, 'privacy' => WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE])[0];
        $uri = '/assets/'.$quarantined->getId().'/quarantine-bypass';

        // A member reading the workspace does not even see the quarantined asset
        $this->request('POST', $uri, self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        // Seeing the quarantine is not enough...
        $this->grantWorkspaceMetadata($workspace, [DataboxExtraPermissionInterface::PERM_QUARANTINE]);
        $data = $this->request('GET', '/assets/'.$quarantined->getId(), self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['capabilities']['bypassQuarantine']);
        $this->request('POST', $uri, self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        // ...the bypass must be granted too
        $this->grantWorkspaceMetadata($workspace, [
            DataboxExtraPermissionInterface::PERM_QUARANTINE,
            DataboxExtraPermissionInterface::PERM_QUARANTINE_BY_PASS,
        ]);
        $data = $this->request('GET', '/assets/'.$quarantined->getId(), self::OWNER)->toArray();
        $this->assertTrue($data['capabilities']['bypassQuarantine']);
        $this->request('POST', $uri, self::OWNER);
        $this->assertResponseIsSuccessful();
        $this->assertSame(AssetStatusEnum::Accepted, $this->reloadAsset($quarantined->getId())->getStatus());
    }

    public function testBypassAnAssetWithoutSource(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setStatus(AssetStatusEnum::Pending);
        self::getEntityManager()->flush();

        $this->request('POST', '/assets/'.$asset->getId().'/quarantine-bypass', self::OTHER);
        $this->assertResponseIsSuccessful();
        $this->assertSame(AssetStatusEnum::Accepted, $this->reloadAsset($asset->getId())->getStatus());
    }

    public function testDuplicatesOfAQuarantinedAsset(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);
        $duplicate = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER, 'name' => 'Original']);
        $unreadable = $this->createAssetWithSource($workspace, ['ownerId' => self::ADMIN]);
        $trashed = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER, 'deleted' => true]);
        $this->linkDuplicate($file, $duplicate->getSource(), 'checksum');
        $this->linkDuplicate($file, $duplicate->getSource(), 'doc_unique_id');
        $this->linkDuplicate($file, $unreadable->getSource(), 'checksum');
        $this->linkDuplicate($file, $trashed->getSource(), 'checksum');

        $data = $this->request('GET', '/assets/'.$quarantined->getId().'/duplicates', self::OWNER)->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $data['duplicates']);
        $this->assertSame($duplicate->getId(), $data['duplicates'][0]['asset']['id']);
        $this->assertSame('Original', $data['duplicates'][0]['asset']['name']);
        $this->assertEqualsCanonicalizing(['checksum', 'doc_unique_id'], $data['duplicates'][0]['analyzers']);

        // The workspace owner reads every duplicate but the trashed one
        $data = $this->request('GET', '/assets/'.$quarantined->getId().'/duplicates', self::OTHER)->toArray();
        $this->assertEqualsCanonicalizing(
            [$duplicate->getId(), $unreadable->getId()],
            array_map(fn (array $d): string => $d['asset']['id'], $data['duplicates']),
        );
    }

    public function testDuplicatesAccess(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined] = $this->createQuarantinedAsset($workspace);
        $withoutSource = $this->createAsset(['ownerId' => self::OWNER]);

        $data = $this->request('GET', '/assets/'.$withoutSource->getId().'/duplicates', self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $data['duplicates']);

        $this->request('GET', '/assets/'.$quarantined->getId().'/duplicates', null);
        $this->assertResponseStatusCodeSame(401);

        $this->request('GET', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11/duplicates', self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAddAsVersionMergesTheQuarantinedAssetIntoItsDuplicate(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined, $file] = $this->createQuarantinedAsset($workspace);
        $target = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $previousSourceId = $target->getSource()->getId();

        $data = $this->request('POST', '/assets/'.$quarantined->getId().'/add-as-version', self::OTHER, [
            'targetAssetId' => $target->getId(),
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame($target->getId(), $data['id']);
        $this->assertSame($file->getId(), $data['source']['id']);

        // The quarantined asset is gone, its file became the new source of the target
        $this->assertNull($this->reloadAsset($quarantined->getId()));
        $target = $this->reloadAsset($target->getId());
        $this->assertSame($file->getId(), $target->getSource()->getId());
        $versions = self::getEntityManager()->getRepository(AssetFileVersion::class)->findBy(['asset' => $target->getId()]);
        $this->assertCount(1, $versions);
        $this->assertSame($previousSourceId, $versions[0]->getFile()->getId());
    }

    public function testAddAsVersionPermissions(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        [$quarantined] = $this->createQuarantinedAsset($workspace);
        $notEditable = $this->createAssetWithSource($workspace, ['ownerId' => self::OTHER]);
        $editable = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $uri = '/assets/'.$quarantined->getId().'/add-as-version';

        // Bypassing the quarantine is required on the quarantined asset
        $this->request('POST', $uri, self::OWNER, ['targetAssetId' => $editable->getId()]);
        $this->assertResponseStatusCodeSame(403);

        // ...and editing the target asset
        $this->grantWorkspaceMetadata($workspace, [
            DataboxExtraPermissionInterface::PERM_QUARANTINE,
            DataboxExtraPermissionInterface::PERM_QUARANTINE_BY_PASS,
        ]);
        $this->request('POST', $uri, self::OWNER, ['targetAssetId' => $notEditable->getId()]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->reloadAsset($quarantined->getId()));

        $this->request('POST', $uri, self::OWNER, ['targetAssetId' => $editable->getId()]);
        $this->assertResponseIsSuccessful();
    }

    public function testAddAsVersionValidation(): void
    {
        $workspace = $this->createQuarantineWorkspace();
        $otherWorkspace = $this->createAnotherWorkspace('second-ws', self::OTHER);
        [$quarantined] = $this->createQuarantinedAsset($workspace);
        $withoutSource = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $withoutSource->setStatus(AssetStatusEnum::Quarantined);
        $target = $this->createAssetWithSource($workspace, ['ownerId' => self::OWNER]);
        $foreignTarget = $this->createAssetWithSource($otherWorkspace, ['ownerId' => self::OTHER, 'workspace' => $otherWorkspace]);

        $this->request('POST', '/assets/'.$quarantined->getId().'/add-as-version', self::OTHER, []);
        $this->assertResponseStatusCodeSame(422);

        $response = $this->request('POST', '/assets/'.$quarantined->getId().'/add-as-version', self::OTHER, [
            'targetAssetId' => $foreignTarget->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('not in the same workspace', $this->errorMessage($response));

        $response = $this->request('POST', '/assets/'.$withoutSource->getId().'/add-as-version', self::OTHER, [
            'targetAssetId' => $target->getId(),
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('no source file', $this->errorMessage($response));

        $this->assertNotNull($this->reloadAsset($quarantined->getId()));
    }

    public function testAddAsVersionToAnUnknownTarget(): void
    {
        $this->markTestIncomplete('BUG: AddAsAssetVersionProcessor resolves targetAssetId with DoctrineUtil::findStrict() without $throw404, whose \\InvalidArgumentException is answered as a 500 instead of a 404/400 (src/Api/Processor/AddAsAssetVersionProcessor.php:46).');

        $workspace = $this->createQuarantineWorkspace();
        [$quarantined] = $this->createQuarantinedAsset($workspace);

        $this->request('POST', '/assets/'.$quarantined->getId().'/add-as-version', self::OTHER, [
            'targetAssetId' => '7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11',
        ]);
        $this->assertResponseStatusCodeSame(404);
        $this->assertNotNull($this->reloadAsset($quarantined->getId()));
    }

    private function createQuarantineWorkspace(): Workspace
    {
        $workspace = $this->createOwnedWorkspace(self::OTHER);
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());

        return $workspace;
    }

    /**
     * @return array{Asset, File}
     */
    private function createQuarantinedAsset(Workspace $workspace, array $options = []): array
    {
        $file = $this->createFile($workspace, ['checksum' => self::CHECKSUM]);
        $file->setAnalysisResult(File::ANALYSIS_FAILED, [[
            'name' => 'checksum',
            'output' => ['messages' => [[4, 'duplicate_checksum', ['count' => 1, 'limit' => 10]]]],
            'actions' => ['quarantine'],
        ]]);

        $asset = $this->createAsset(['ownerId' => $options['ownerId'] ?? self::OWNER, 'no_flush' => true]);
        $asset->setSource($file);
        $asset->setStatus(AssetStatusEnum::Quarantined);
        $asset->setPrivacy($options['privacy'] ?? WorkspaceItemPrivacyInterface::SECRET);
        self::getEntityManager()->flush();

        return [$asset, $file];
    }

    private function createAssetWithSource(Workspace $workspace, array $options = []): Asset
    {
        $file = $this->createFile($workspace, ['checksum' => self::CHECKSUM]);

        $asset = $this->createAsset(array_merge($options, ['no_flush' => true]));
        $asset->setSource($file);
        if ($options['deleted'] ?? false) {
            $asset->setDeletedAt(new \DateTimeImmutable());
        }
        self::getEntityManager()->flush();

        return $asset;
    }

    private function linkDuplicate(File $file, File $duplicateFile, string $analyzer): void
    {
        $em = self::getEntityManager();
        $link = new FileDuplicate();
        $link->setFile($em->getReference(File::class, $file->getId()));
        $link->setDuplicateFile($em->getReference(File::class, $duplicateFile->getId()));
        $link->setAnalyzer($analyzer);
        $em->persist($link);
        $em->flush();
    }

    /**
     * Grants USER_UID (member) extra permissions on the workspace.
     *
     * @param int[] $metadata
     */
    private function grantWorkspaceMetadata(Workspace $workspace, array $metadata): void
    {
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            self::OWNER,
            'workspace',
            $workspace->getId(),
            PermissionInterface::VIEW,
            $metadata,
        );
    }
}
