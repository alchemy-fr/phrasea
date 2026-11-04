<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\FileDuplicate;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Files of an asset: GET /asset-file-versions(/{id}),
 * DELETE /asset-file-versions/{id}, GET /files/{id}, /files/{id}/metadata and
 * /files/{id}/duplicates.
 */
final class AssetFileTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testListFileVersionsOfAnAsset(): void
    {
        $workspace = $this->createOwnedWorkspace();
        [$asset, $v1, $v2] = $this->createAssetWithVersions($workspace);

        $response = $this->request('GET', '/asset-file-versions', self::OWNER, null, [
            'query' => ['assetId' => $asset->getId()],
        ]);

        $this->assertResponseIsSuccessful();
        $members = $response->toArray()['member'];
        // Most recent first
        $this->assertSame([$v2->getId(), $v1->getId()], array_column($members, 'id'));
        $this->assertSame('/assets/'.$asset->getId(), $members[0]['asset']['@id']);
        $this->assertSame($v2->getFile()->getId(), $members[0]['file']['id']);
        $this->assertSame('v2', $members[0]['name']);
        $this->assertMatchesRegularExpression('#^v-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$#', $members[1]['name']);
    }

    public function testListFileVersionsRequiresAReadableAsset(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        [$asset] = $this->createAssetWithVersions($workspace);

        $this->request('GET', '/asset-file-versions', self::OWNER);
        $this->assertResponseStatusCodeSame(400);

        $this->request('GET', '/asset-file-versions', self::OTHER, null, ['query' => ['assetId' => $asset->getId()]]);
        $this->assertResponseStatusCodeSame(403);

        $this->request('GET', '/asset-file-versions', self::OWNER, null, ['query' => ['assetId' => '7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11']]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetFileVersion(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        [$asset, $version] = $this->createAssetWithVersions($workspace);

        $data = $this->request('GET', '/asset-file-versions/'.$version->getId(), self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame($version->getId(), $data['id']);
        $this->assertSame($version->getFile()->getId(), $data['file']['id']);

        $this->request('GET', '/asset-file-versions/'.$version->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        // Readers of the asset read its versions
        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::VIEW);
        $this->request('GET', '/asset-file-versions/'.$version->getId(), self::OTHER);
        $this->assertResponseIsSuccessful();
    }

    public function testDeleteFileVersion(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        [$asset, $version, $kept] = $this->createAssetWithVersions($workspace);
        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::VIEW | PermissionInterface::OPERATOR);

        // Editing the asset is not enough
        $this->request('DELETE', '/asset-file-versions/'.$version->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('DELETE', '/asset-file-versions/'.$version->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(AssetFileVersion::class, $version->getId()));
        $this->assertNotNull($em->find(AssetFileVersion::class, $kept->getId()));
        $this->assertNotNull($em->find(Asset::class, $asset->getId())->getSource());
    }

    public function testDeletingTheAssetDeletesItsVersions(): void
    {
        $workspace = $this->createOwnedWorkspace();
        [$asset, $v1, $v2] = $this->createAssetWithVersions($workspace);

        $this->request('DELETE', '/assets/'.$asset->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(AssetFileVersion::class, $v1->getId()));
        $this->assertNull($em->find(AssetFileVersion::class, $v2->getId()));
    }

    public function testGetFileOfAnAsset(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $file = $this->createFile($workspace, ['originalName' => 'picture.jpg', 'size' => 2048]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'name' => 'Holder', 'no_flush' => true]);
        $asset->setSource($file);
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/files/'.$file->getId(), self::OWNER)->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame($file->getId(), $data['id']);
        $this->assertSame('image/jpeg', $data['type']);
        $this->assertSame(2048, $data['size']);
        $this->assertSame($file->getPath(), $data['url']);
        $this->assertTrue($data['accepted']);
        $this->assertSame([[
            'type' => 'source',
            'assetId' => $asset->getId(),
            'assetTitle' => 'Holder',
        ]], $data['usages']);
        $this->assertArrayNotHasKey('metadata', $data);

        // Same access rule as the asset
        $this->request('GET', '/files/'.$file->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/files/'.$file->getId(), null);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testFileOfAPublicAssetIsReadableByAnonymous(): void
    {
        $workspace = $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $file = $this->createFile($workspace);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'public' => true, 'no_flush' => true]);
        $asset->setSource($file);
        self::getEntityManager()->flush();

        $this->request('GET', '/files/'.$file->getId(), null);
        $this->assertResponseIsSuccessful();
    }

    public function testFileNotUsedByAnyAssetIsNotReadable(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $file = $this->createFile($workspace);

        // Only an admin can read it
        $this->request('GET', '/files/'.$file->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('GET', '/files/'.$file->getId(), self::ADMIN);
        $this->assertResponseIsSuccessful();

        $this->request('GET', '/files/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFileKeptAsAVersionIsReadable(): void
    {
        $this->markTestIncomplete('BUG: FileVoter only looks for assets using the file as source or rendition, not as an AssetFileVersion: a previous source of an asset cannot be read through GET /files/{id} by the asset owner, although FileOutputMapper::resolveUsages() lists "version" usages (src/Security/Voter/FileVoter.php:34).');

        $workspace = $this->createOwnedWorkspace();
        [, $version] = $this->createAssetWithVersions($workspace);

        $this->request('GET', '/files/'.$version->getFile()->getId(), self::OWNER);
        $this->assertResponseIsSuccessful();
    }

    public function testGetFileMetadata(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $file = $this->createFile($workspace, ['metadata' => ['Composite:ImageSize' => ['value' => '800x600']]]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setSource($file);
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/files/'.$file->getId().'/metadata', self::OWNER)->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertSame(['Composite:ImageSize' => ['value' => '800x600']], $data['metadata']);

        $this->request('GET', '/files/'.$file->getId().'/metadata', self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testFileDuplicates(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $file = $this->createFile($workspace);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setSource($file);
        $duplicateFile = $this->createFile($workspace);
        $duplicate = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $duplicate->setSource($duplicateFile);
        self::getEntityManager()->flush();

        $link = new FileDuplicate();
        $link->setFile($file);
        $link->setDuplicateFile($duplicateFile);
        $link->setAnalyzer('checksum');
        self::getEntityManager()->persist($link);
        self::getEntityManager()->flush();

        $data = $this->request('GET', '/files/'.$file->getId().'/duplicates', self::OWNER)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame([$duplicate->getId()], array_map(fn (array $d): string => $d['asset']['id'], $data['duplicates']));
        $this->assertSame(['checksum'], $data['duplicates'][0]['analyzers']);

        $this->request('GET', '/files/'.$file->getId().'/duplicates', self::OTHER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('GET', '/files/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11/duplicates', self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * @return array{Asset, AssetFileVersion, AssetFileVersion}
     */
    private function createAssetWithVersions(Workspace $workspace): array
    {
        $em = self::getEntityManager();
        $source = $this->createFile($workspace);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setSource($source);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::SECRET);

        $versions = [];
        foreach ([null, 'v2'] as $i => $name) {
            $version = new AssetFileVersion();
            $version->setAsset($asset);
            $version->setFile($this->createFile($workspace));
            $version->setVersionName($name);
            // Gedmo Timestampable keeps manually set values
            (new \ReflectionProperty(AssetFileVersion::class, 'createdAt'))
                ->setValue($version, new \DateTimeImmutable(sprintf('2026-01-0%d 10:00:00', $i + 1)));
            $em->persist($version);
            $versions[] = $version;
        }
        $em->flush();

        return [$asset, ...$versions];
    }
}
