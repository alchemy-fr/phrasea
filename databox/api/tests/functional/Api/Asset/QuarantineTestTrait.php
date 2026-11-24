<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;

/**
 * Quarantined assets and their duplicates, for the tests using AssetApiTestTrait.
 *
 * The workspace is owned by OTHER_USER_UID (who may bypass the quarantine);
 * USER_UID is a member owning the quarantined assets.
 */
trait QuarantineTestTrait
{
    protected const string CHECKSUM = 'a3f5c2e1d4b6a798a3f5c2e1d4b6a798a3f5c2e1d4b6a798a3f5c2e1d4b6a798';

    protected function createQuarantineWorkspace(): Workspace
    {
        $workspace = $this->createOwnedWorkspace(self::OTHER);
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());

        return $workspace;
    }

    /**
     * @return array{Asset, File}
     */
    protected function createQuarantinedAsset(Workspace $workspace, array $options = []): array
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

    protected function createAssetWithSource(Workspace $workspace, array $options = []): Asset
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

    protected function linkDuplicate(File $file, File $duplicateFile, string $analyzer): void
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
    protected function grantWorkspaceMetadata(Workspace $workspace, array $metadata): void
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
