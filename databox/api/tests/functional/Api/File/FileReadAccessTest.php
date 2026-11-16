<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\File;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AssetRelationship;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\File;
use App\Entity\Core\ImageSet;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Service\Workspace\TermsManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Asset\AssetApiTestTrait;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;

/**
 * GET /files/{id} (and /metadata, /duplicates): a file is readable by whoever can
 * read an entity using it (see FileVoter), complementing AssetFileTest (asset source
 * and versions) and AssetSourceFileCopyTest (copy as the source of a new asset).
 */
final class FileReadAccessTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;
    use RenditionTestTrait;

    public function testARenditionFileIsReadThroughItsPolicy(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());

        $source = $this->createFile($workspace);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $asset->setSource($source);
        self::getEntityManager()->flush();

        $policy = $this->createRenditionPolicy($workspace, 'Private', public: false);
        $definition = $this->createRenditionDefinition($workspace, $policy, 'hd');
        $file = $this->createFile($workspace);
        $this->createAssetRendition($asset, $definition, $file);

        // The asset is readable, not its rendition
        $this->request('GET', '/files/'.$source->getId(), self::OTHER);
        $this->assertResponseIsSuccessful();
        $this->assertFileStatus(403, $file, self::OTHER, withSubResources: true);

        $this->grantUserOnObject(self::OTHER, $policy, PermissionInterface::CHILD_VIEW);
        $this->assertFileStatus(200, $file, self::OTHER, withSubResources: true);
    }

    public function testTheImageOfAnEntityListValueIsReadByTheReadersOfTheValue(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace();

        $list = new EntityList();
        $list->setName('People');
        $list->setWorkspace($workspace);
        $em->persist($list);
        $entity = new AttributeEntity();
        $entity->setList($list);
        $entity->setValue('Alice');
        $entity->setStatus(AttributeEntity::STATUS_APPROVED);
        $image = $this->createImageSet($workspace);
        $entity->setImage($image);
        $em->persist($entity);
        $em->flush();

        foreach ([$image->getImageFile(), $image->getThumbnailFile()] as $file) {
            $this->assertFileStatus(200, $file, self::OWNER);
            $this->assertFileStatus(403, $file, self::OTHER);
        }
    }

    public function testTheLogoOfAWorkspaceIsReadByItsMembers(): void
    {
        $workspace = $this->createMemberWorkspace();
        $logo = $this->createImageSet($workspace);
        $workspace->setLogo($logo);
        self::getEntityManager()->flush();

        $this->assertFileStatus(200, $logo->getImageFile(), self::OWNER);
        $this->assertFileStatus(403, $logo->getImageFile(), self::OTHER);
    }

    public function testATermsFileIsReadByWhoCanReadTheWorkspace(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createMemberWorkspace();
        $file = $this->createFile($workspace, ['type' => 'application/pdf']);
        $terms = new TermsVersion();
        $terms->setWorkspace($workspace);
        $terms->setText('Be nice');
        $terms->setFile($file);
        $em->persist($terms);
        $em->flush();
        self::getService(TermsManager::class)->sign($terms, self::OWNER);

        $this->assertFileStatus(200, $file, self::OWNER);
        $this->assertFileStatus(403, $file, self::OTHER);
    }

    public function testAFileOnlyUsedByAnAssetRelationshipIsNeitherReadNorCopied(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->createOwnedWorkspace();
        $source = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $source->setSource($this->createFile($workspace));
        $target = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $target->setSource($this->createFile($workspace));
        $file = $this->createFile($workspace);
        $relationship = new AssetRelationship();
        $relationship->setType('derived');
        $relationship->setSource($source);
        $relationship->setTarget($target);
        $relationship->setSourceFile($file);
        $em->persist($relationship);
        $em->flush();

        $this->assertFileStatus(403, $file, self::OWNER);

        $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'sourceFileId' => $file->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    private function assertFileStatus(int $expected, File $file, string $userId, bool $withSubResources = false): void
    {
        $uris = ['/files/'.$file->getId()];
        if ($withSubResources) {
            $uris[] = '/files/'.$file->getId().'/metadata';
            $uris[] = '/files/'.$file->getId().'/duplicates';
        }

        foreach ($uris as $uri) {
            $this->request('GET', $uri, $userId);
            $this->assertResponseStatusCodeSame($expected, $uri);
        }
    }

    /**
     * A workspace of another user, OWNER being one of its readers and OTHER none.
     */
    private function createMemberWorkspace(): Workspace
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $this->addUserOnWorkspace(self::OWNER, $workspace->getId());

        return $workspace;
    }

    private function createImageSet(Workspace $workspace): ImageSet
    {
        $image = new ImageSet();
        $image->setWorkspace($workspace);
        $image->setImageFile($this->createFile($workspace));
        $image->setThumbnailFile($this->createFile($workspace));
        self::getEntityManager()->persist($image);

        return $image;
    }
}
