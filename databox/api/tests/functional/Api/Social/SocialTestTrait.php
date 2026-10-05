<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;

/**
 * Helpers shared by the sharing / discussion / attachment tests.
 */
trait SocialTestTrait
{
    /**
     * @return array<string, array<string, string>>
     */
    private static function auth(?string $userId, array $options = []): array
    {
        if (null === $userId) {
            return $options;
        }

        $options['headers'] = ($options['headers'] ?? []) + [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];

        return $options;
    }

    /**
     * Workspaces created by the shared helper all get the same slug, which is unique.
     */
    private function createNamedWorkspace(string $ownerId, string $slug, bool $public = false): Workspace
    {
        $workspace = $this->createWorkspace([
            'ownerId' => $ownerId,
            'name' => $slug,
            'no_flush' => true,
            'public' => $public,
        ]);
        $workspace->setSlug($slug);
        self::getEntityManager()->flush();

        return $workspace;
    }

    private function createUrlFile(Workspace $workspace, string $url, string $type = 'image/jpeg', int $size = 1234): File
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace(self::managed($workspace));
        $file->setStorage(File::STORAGE_URL);
        $file->setPath($url);
        $file->setType($type);
        $file->setSize($size);
        $em->persist($file);
        $em->flush();

        return $file;
    }

    private function createRenditionDefinition(Workspace $workspace, string $name, bool $publicPolicy): RenditionDefinition
    {
        $em = self::getEntityManager();

        $policy = new RenditionPolicy();
        $workspace = self::managed($workspace);
        $policy->setWorkspace($workspace);
        $policy->setName($name.'-policy');
        $policy->setPublic($publicPolicy);
        $policy->setEditable(false);
        $em->persist($policy);

        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName($name);
        $definition->setTarget(AssetTypeEnum::Both);
        $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
        $em->persist($definition);
        $em->flush();

        return $definition;
    }

    private function createRendition(Asset $asset, RenditionDefinition $definition, File $file): AssetRendition
    {
        $em = self::getEntityManager();

        $rendition = new AssetRendition();
        $rendition->setAsset(self::managed($asset));
        $rendition->setDefinition(self::managed($definition));
        $rendition->setFile(self::managed($file));
        $em->persist($rendition);
        $em->flush();

        return $rendition;
    }

    private function createAssetAttachment(Asset $asset, Asset $attached, ?string $name = null): AssetAttachment
    {
        $em = self::getEntityManager();

        $attachment = new AssetAttachment();
        $attachment->setAsset(self::managed($asset));
        $attachment->setAttachment(self::managed($attached));
        $attachment->setName($name);
        $em->persist($attachment);
        $em->flush();

        return $attachment;
    }

    /**
     * @param Asset[] $assets
     */
    private function createShare(string $ownerId, array $assets, array $options = []): Share
    {
        $em = self::getEntityManager();

        $share = new Share();
        $share->setOwnerId($ownerId);
        foreach ($assets as $asset) {
            $share->addAsset(self::managed($asset));
        }
        $share->setName($options['name'] ?? null);
        $share->setEnabled($options['enabled'] ?? true);
        $share->setStartsAt($options['startsAt'] ?? null);
        $share->setExpiresAt($options['expiresAt'] ?? null);
        $em->persist($share);
        $em->flush();

        return $share;
    }

    /**
     * Entities created before a request belong to the EntityManager of the
     * previous kernel: re-fetch them from the current one.
     *
     * @template T of AbstractUuidEntity
     *
     * @param T $entity
     *
     * @return T
     */
    private static function managed(AbstractUuidEntity $entity): AbstractUuidEntity
    {
        $em = self::getEntityManager();
        if ($em->contains($entity)) {
            return $entity;
        }

        return $em->find($entity::class, $entity->getId());
    }

    private static function assetIri(Asset $asset): string
    {
        return '/assets/'.$asset->getId();
    }
}
