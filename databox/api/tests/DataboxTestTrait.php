<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\TagFilterManager;
use Ramsey\Uuid\Uuid;

trait DataboxTestTrait
{
    protected ?Workspace $defaultWorkspace = null;
    protected ?AttributePolicy $defaultAttributePolicy = null;

    protected function createAsset(array $options = []): Asset
    {
        $em = self::getEntityManager();

        $asset = new Asset();
        $asset->setTitle($options['title'] ?? null);
        $workspace = $options['workspace'] ?? $this->getOrCreateDefaultWorkspace();
        $asset->setWorkspace($workspace);
        $asset->setOwnerId($options['ownerId'] ?? 'custom_owner');

        if ($options['public'] ?? false) {
            $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC);
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
                throw new \InvalidArgumentException('Collection not found');
            }
            $asset->setReferenceCollection($collection);
            $collectionAsset = $asset->addToCollection($collection);
            $em->persist($collectionAsset);
        }

        if (isset($options['attributes'])) {
            /** @var AttributeTypeRegistry $typeRegistry */
            $typeRegistry = self::getContainer()->get(AttributeTypeRegistry::class);

            foreach ($options['attributes'] as $attr) {
                $a = new Attribute();
                $a->setAsset($asset);
                $a->setDefinition($attr['definition']);
                $a->setLocale($attr['locale'] ?? null);
                $a->setOrigin($attr['origin'] ?? Attribute::ORIGIN_MACHINE);
                $a->setValue($typeRegistry->getStrictType($attr['definition']->getFieldType())->normalizeValue($attr['value']));

                $em->persist($a);
            }
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
        $collection->setWorkspace($options['workspace'] ?? $this->getOrCreateDefaultWorkspace());
        $collection->setTitle($options['title'] ?? null);
        $collection->setOwnerId($options['ownerId'] ?? 'custom_owner');

        if ($options['public'] ?? false) {
            $collection->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC);
        }
        if ($options['parent'] ?? false) {
            $collection->setParent($options['parent']);
        }

        $em->persist($collection);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $collection;
    }

    protected function createAttributeDefinition(array $options = []): AttributeDefinition
    {
        $em = self::getEntityManager();

        $definition = new AttributeDefinition();
        $definition->setPolicy($options['policy'] ?? $this->getOrCreateDefaultAttributePolicy([
            'no_flush' => $options['no_flush'] ?? null,
            'workspace' => $options['workspace'] ?? null,
        ]));
        $definition->setWorkspace($options['workspace'] ?? $this->getOrCreateDefaultWorkspace());
        $definition->setFieldType($options['type'] ?? TextAttributeType::NAME);
        $definition->setTranslatable($options['translatable'] ?? false);
        $definition->setMultiple($options['multiple'] ?? false);
        $definition->setSearchable($options['searchable'] ?? true);
        $definition->setName($options['name'] ?? true);
        $definition->setFallback($options['fallback'] ?? null);

        $em->persist($definition);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $definition;
    }

    protected function createAttributePolicy(array $options = []): AttributePolicy
    {
        $em = self::getEntityManager();

        $attributeClass = new AttributePolicy();
        $attributeClass->setWorkspace($options['workspace'] ?? $this->getOrCreateDefaultWorkspace());
        $attributeClass->setEditable($options['editable'] ?? true);
        $attributeClass->setPublic($options['public'] ?? true);
        $attributeClass->setName($options['name']);

        $em->persist($attributeClass);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $attributeClass;
    }

    protected function grantUserOnObject(string $userId, AclObjectInterface $object, int $permission): void
    {
        self::getPermissionManager()->grantUserOnObject($userId, $object, $permission);
    }

    protected function getDataFromResponse($response, ?int $expectedCode)
    {
        if ($response->getStatusCode() !== $expectedCode) {
            dump($response->getContent());
        }
        $this->assertEquals($expectedCode, $response->getStatusCode());

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function createWorkspace(array $options = []): Workspace
    {
        $em = self::getEntityManager();

        $workspace = new Workspace();
        $workspace->setName($options['name'] ?? 'My workspace');
        $ownerId = $options['ownerId'] ?? 'custom_owner';
        $workspace->setOwnerId($ownerId);
        $workspace->setEnabledLocales(['fr', 'en', 'de']);
        $workspace->setSlug('my-workspace');
        if ($options['public'] ?? false) {
            $workspace->setPublic(true);
        }

        $em->persist($workspace);

        if (!($options['no_acl'] ?? false)) {
            $this->addUserOnWorkspace($ownerId, $workspace->getId());
        }

        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $workspace;
    }

    protected function addUserOnWorkspace(string $ownerId, string $workspaceId): void
    {
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            $ownerId,
            'workspace',
            $workspaceId,
            PermissionInterface::VIEW
        );
    }

    protected static function getPermissionManager(): PermissionManager
    {
        return self::getService(PermissionManager::class);
    }

    protected static function getTagFilterManager(): TagFilterManager
    {
        return self::getContainer()->get(TagFilterManager::class);
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

    protected function findOrCreateTagByName(string $name, ?Workspace $workspace): ?Tag
    {
        $em = self::getEntityManager();
        /** @var Tag $tag */
        $tag = $em->getRepository(Tag::class)->findOneBy([
            'workspace' => $workspace ?? $this->getOrCreateDefaultWorkspace(),
            'name' => $name,
        ]);

        if (null === $tag) {
            $tag = new Tag();
            $tag->setWorkspace($workspace ?? $this->getOrCreateDefaultWorkspace());
            $tag->setName($name);

            $em->persist($tag);
            $em->flush();
        }

        return $tag;
    }

    protected function getOrCreateDefaultWorkspace(): Workspace
    {
        if (null !== $this->defaultWorkspace) {
            return $this->defaultWorkspace;
        }

        return $this->defaultWorkspace = $this->createWorkspace();
    }

    protected function getOrCreateDefaultAttributePolicy(array $options = []): AttributePolicy
    {
        if (null !== $this->defaultAttributePolicy) {
            return $this->defaultAttributePolicy;
        }

        return $this->defaultAttributePolicy = $this->createAttributePolicy(array_merge([
            'name' => 'Default',
        ], $options));
    }
}
