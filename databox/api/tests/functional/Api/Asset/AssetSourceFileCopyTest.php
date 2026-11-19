<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Discussion\Message;
use App\Entity\Discussion\Thread;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\File\OrphanFileRemover;
use App\Integration\ToastUi\TuiPhotoEditorIntegration;
use App\Model\AssetTypeEnum;
use App\Security\Voter\FileVoter;
use App\Service\Discussion\MessageAttachmentManager;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * A new asset may copy an existing file ("sourceFileId") only when the user
 * can read that file where it is used: the source or a previous source of a
 * readable asset, a rendition the rendition policy lets the user read, an
 * integration output of a readable file… (see FileVoter). Any other file is refused.
 */
class AssetSourceFileCopyTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    public function testOnlyTheFileOfAReadableAssetIsCopied(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);

        $secretFile = $this->createSourceFile($workspace);
        $secret = $this->createAsset(['ownerId' => self::ADMIN, 'no_flush' => true]);
        $secret->setSource($secretFile);
        $mineFile = $this->createSourceFile($workspace);
        $mine = $this->createAsset(['ownerId' => self::USER, 'no_flush' => true]);
        $mine->setSource($mineFile);
        $em->flush();

        $this->assertCopyStatus(403, $workspace, $secretFile);
        $this->assertCopyStatus(201, $workspace, $mineFile);
    }

    public function testARenditionIsCopiedOnlyIfItsPolicyLetsTheUserReadIt(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);

        $asset = $this->createAsset(['ownerId' => self::USER, 'no_flush' => true]);
        $asset->setSource($this->createSourceFile($workspace));
        $policy = $this->createRenditionPolicy($workspace, false);
        $file = $this->createSourceFile($workspace);
        $this->createRendition($asset, $policy, $file);
        $em->flush();

        // The asset is readable, not its rendition
        $this->assertCopyStatus(403, $workspace, $file);

        $this->grantUserOnObject(self::USER, $policy, PermissionInterface::CHILD_VIEW);
        $this->assertCopyStatus(201, $workspace, $file);
    }

    public function testARenditionOfAPublicPolicyIsCopied(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);

        $asset = $this->createAsset(['ownerId' => self::USER, 'no_flush' => true]);
        $file = $this->createSourceFile($workspace);
        $this->createRendition($asset, $this->createRenditionPolicy($workspace, true), $file);
        $em->flush();

        $this->assertCopyStatus(201, $workspace, $file);
    }

    public function testAPreviousSourceIsCopiedOnlyFromAReadableAsset(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);

        $mine = $this->createAsset(['ownerId' => self::USER, 'no_flush' => true]);
        $mineVersion = $this->createVersion($mine, $workspace);
        $secret = $this->createAsset(['ownerId' => self::ADMIN, 'no_flush' => true]);
        $secretVersion = $this->createVersion($secret, $workspace);
        $em->flush();

        $this->assertCopyStatus(403, $workspace, $secretVersion->getFile());
        $this->assertCopyStatus(201, $workspace, $mineVersion->getFile());
    }

    public function testAFileUsedNowhereIsNotCopied(): void
    {
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);
        $file = $this->createSourceFile($workspace);
        self::getEntityManager()->flush();

        $this->assertCopyStatus(403, $workspace, $file);
    }

    public function testAMessageAttachmentIsNeitherCopiedNorRemovedAsAnOrphan(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);

        // A file attached by the admin to a message on an asset the user can read
        $asset = $this->createAsset(['ownerId' => self::USER, 'no_flush' => true]);
        $file = $this->createSourceFile($workspace);
        $thread = new Thread();
        $thread->setKey('asset:'.$asset->getId());
        $em->persist($thread);
        $message = new Message();
        $message->setThread($thread);
        $message->setAuthorId(self::ADMIN);
        $message->setContent('See attached');
        $message->setAttachments([[
            'type' => MessageAttachmentManager::TYPE_FILE,
            'content' => json_encode(['id' => $file->getId(), 'name' => 'secret.jpg'], JSON_THROW_ON_ERROR),
        ]]);
        $message->addFile($file);
        $em->persist($message);
        $em->flush();

        $this->assertCopyStatus(403, $workspace, $file);

        $this->assertFalse(self::getService(OrphanFileRemover::class)->removeIfOrphan($file->getId()));
        $em->clear();
        $this->assertNotNull($em->find(File::class, $file->getId()));
    }

    public function testAFileUsedNowhereIsRemovedAsAnOrphan(): void
    {
        $workspace = $this->createMemberWorkspace();
        $file = $this->createSourceFile($workspace);
        // Nothing to delete from the storage
        $file->setStorage(File::STORAGE_URL);
        self::getEntityManager()->flush();

        $this->assertTrue(self::getService(OrphanFileRemover::class)->removeIfOrphan($file->getId()));
    }

    public function testAnIntegrationOutputIsCopiedByWhoCanSeeIt(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace(PermissionInterface::CHILD_CREATE);
        $this->grantUserOnObject(self::OTHER, $workspace, PermissionInterface::VIEW | PermissionInterface::CHILD_CREATE);

        // An asset both users can read, edited by none of them
        $asset = $this->createAsset(['ownerId' => self::ADMIN, 'no_flush' => true]);
        $input = $this->createSourceFile($workspace);
        $asset->setSource($input);
        $em->flush();
        $this->grantUserOnObject(self::USER, $asset, PermissionInterface::VIEW);
        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::VIEW);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setIntegration(TuiPhotoEditorIntegration::NAME);
        $integration->setOwnerId(self::ADMIN);
        $integration->setEnabled(true);
        $integration->setPublic(false);
        $em->persist($integration);
        $output = $this->createSourceFile($workspace);
        $data = new IntegrationData();
        $data->setIntegration($integration);
        $data->setUserId(self::USER);
        $data->setObject($input);
        $data->setName(FileVoter::INTEGRATION_OUTPUT_DATA_NAME);
        $data->setValue($output->getId());
        $em->persist($data);
        $em->flush();

        // The output of another user, who only reads the input file
        $this->assertCopyStatus(403, $workspace, $output, self::OTHER);
        $this->assertCopyStatus(201, $workspace, $output);

        // Still referenced by the integration data
        $this->assertFalse(self::getService(OrphanFileRemover::class)->removeIfOrphan($output->getId()));
    }

    private function assertCopyStatus(int $expected, Workspace $workspace, File $file, string $userId = self::USER): void
    {
        self::getEntityManager()->clear();
        static::createClient()->request('POST', '/assets', [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)],
            'json' => [
                'workspace' => '/workspaces/'.$workspace->getId(),
                'sourceFileId' => $file->getId(),
            ],
        ]);
        $this->assertResponseStatusCodeSame($expected);
    }

    private function createSourceFile(Workspace $workspace): File
    {
        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $file->setType('image/jpeg');
        self::getEntityManager()->persist($file);

        return $file;
    }

    private function createRenditionPolicy(Workspace $workspace, bool $public): RenditionPolicy
    {
        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName($public ? 'Public policy' : 'Private policy');
        $policy->setEditable(false);
        $policy->setPublic($public);
        self::getEntityManager()->persist($policy);

        return $policy;
    }

    private function createRendition(Asset $asset, RenditionPolicy $policy, File $file): AssetRendition
    {
        $em = self::getEntityManager();

        $definition = new RenditionDefinition();
        $definition->setWorkspace($policy->getWorkspace());
        $definition->setPolicy($policy);
        $definition->setName('hd-'.uniqid());
        $definition->setTarget(AssetTypeEnum::Both);
        $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
        $em->persist($definition);

        $rendition = new AssetRendition();
        $rendition->setAsset($asset);
        $rendition->setDefinition($definition);
        $rendition->setFile($file);
        $em->persist($rendition);

        return $rendition;
    }

    private function createVersion(Asset $asset, Workspace $workspace): AssetFileVersion
    {
        $version = new AssetFileVersion();
        $version->setAsset($asset);
        $version->setFile($this->createSourceFile($workspace));
        self::getEntityManager()->persist($version);

        return $version;
    }

    /**
     * A workspace of the admin, the user being one of its readers (and more if $extraPermissions).
     */
    private function createMemberWorkspace(int $extraPermissions = 0): Workspace
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::ADMIN]);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW | $extraPermissions);

        return $workspace;
    }
}
