<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Security\TagFilterManager;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

abstract class AbstractDataboxTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    private ?Workspace $defaultWorkspaceId = null;

    protected function createAsset(array $options = []): Asset
    {
        $em = self::getEntityManager();

        $asset = new Asset();
        $asset->setTitle($options['title'] ?? null);
        $workspace = $options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace();
        $asset->setWorkspace($workspace);
        $asset->setOwnerId($options['ownerId'] ?? 'custom_owner');

        if (isset($options['public'])) {
            $asset->setPublic($options['public']);
        }

        foreach ($options['tags'] ?? [] as $tagName) {
            $repo = $em->getRepository(Tag::class);
            if (Uuid::isValid($tagName)) {
                $tag = $repo->find($tagName);
            } else {
                if (null === $tag = $repo->findOneBy([
                    'workspace' => $workspace->getId(),
                    'name' => $tagName,
                ])) {
                    $tag = new Tag();
                    $tag->setName($tagName);
                    $tag->setWorkspace($workspace);
                    $em->persist($tag);
                }
            }
            $asset->addTag($tag);
        }

        if (isset($options['collectionId'])) {
            $collection = $em->find(Collection::class, $options['collectionId']);
            if (!$collection instanceof Collection) {
                throw new InvalidArgumentException('Collection not found');
            }
            $collectionAsset = $asset->addToCollection($collection);
            $em->persist($collectionAsset);
        }

        $em->persist($asset);

        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $asset;
    }

    protected function createCollection(array $options = []): Collection
    {
        $em = self::getEntityManager();

        $collection = new Collection();
        $collection->setWorkspace($options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace());
        $collection->setTitle($options['title'] ?? null);
        $collection->setWorkspace($options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace());
        $collection->setOwnerId($options['ownerId'] ?? 'custom_owner');

        if (isset($options['public'])) {
            $collection->setPublic($options['public']);
        }

        $em->persist($collection);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $collection;
    }

    protected function grantUserOnObject(string $userId, AclObjectInterface $object, int $permission, array $options = []): void
    {
        self::getPermissionManager()->grantUserOnObject($userId, $object, $permission);
    }

    protected function getDataFromResponse($response, ?int $expectedCode)
    {
        $this->assertEquals($expectedCode, $response->getStatusCode());

        return \GuzzleHttp\json_decode($response->getContent(), true);
    }

    protected function createWorkspace(array $options = []): Workspace
    {
        $em = self::getEntityManager();

        $workspace = new Workspace();
        $workspace->setName($options['name'] ?? 'My workspace');

        $em->persist($workspace);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $workspace;
    }

    protected static function getPermissionManager(): PermissionManager
    {
        return self::$container->get(PermissionManager::class);
    }

    protected static function getTagFilterManager(): TagFilterManager
    {
        return self::$container->get(TagFilterManager::class);
    }

    protected function addAssetToCollection(string $collectionId, string $assetId, array $options = []): string
    {
        $em = self::getEntityManager();

        $collectionAsset = new CollectionAsset();
        $collectionAsset->setCollection($em->getReference(Collection::class, $collectionId));
        $collectionAsset->setAsset($em->getReference(Asset::class, $assetId));

        $em->persist($collectionAsset);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $collectionAsset->getId();
    }

    protected function findAsset(string $id): ?Asset
    {
        $em = self::getEntityManager();
        /** @var Asset $asset */
        $asset = $em->find(Asset::class, $id);

        return $asset;
    }

    protected function findTagByName(string $name): ?Tag
    {
        $em = self::getEntityManager();
        /** @var Tag $tag */
        $tag = $em->getRepository(Tag::class)->findOneBy([
            'workspace' => $this->defaultWorkspaceId,
            'name' => $name,
        ]);

        return $tag;
    }

    protected function getOrCreateDefaultWorkspace(): Workspace
    {
        if (null !== $this->defaultWorkspaceId) {
            return $this->defaultWorkspaceId;
        }

        return $this->defaultWorkspaceId = $this->createWorkspace();
    }

    protected function clearEmBeforeApiCall(): void
    {
        self::getEntityManager()->clear();
    }
}
