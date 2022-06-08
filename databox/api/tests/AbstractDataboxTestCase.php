<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\ApiTest\ApiTestCase;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\TagFilterManager;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractDataboxTestCase extends ApiTestCase
{
    use FixturesTrait;

    private ?Workspace $defaultWorkspace = null;
    private ?AttributeClass $defaultAttributeClass = null;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::fixturesBootKernel($options);
    }

    protected function createAsset(array $options = []): Asset
    {
        $em = self::getEntityManager();

        $asset = new Asset();
        $asset->setTitle($options['title'] ?? null);
        $workspace = $options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace();
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
                throw new InvalidArgumentException('Collection not found');
            }
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
        $collection->setWorkspace($options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace());
        $collection->setTitle($options['title'] ?? null);
        $collection->setOwnerId($options['ownerId'] ?? 'custom_owner');

        if ($options['public'] ?? false) {
            $collection->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC);
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
        $definition->setClass($options['class'] ?? $this->getOrCreateDefaultAttributeClass([
            'no_flush' => $options['no_flush'] ?? null,
        ]));
        $definition->setWorkspace($options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace());
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

    protected function createAttributeClass(array $options = []): AttributeClass
    {
        $em = self::getEntityManager();

        $attributeClass = new AttributeClass();
        $attributeClass->setWorkspace($options['workspaceId'] ?? $this->getOrCreateDefaultWorkspace());
        $attributeClass->setEditable($options['editable'] ?? true);
        $attributeClass->setPublic($options['public'] ?? true);
        $attributeClass->setName($options['name']);

        $em->persist($attributeClass);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $attributeClass;
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
        $ownerId = $options['ownerId'] ?? 'custom_owner';
        $workspace->setOwnerId($ownerId);
        $workspace->setEnabledLocales(['fr', 'en', 'de']);
        $workspace->setSlug('my-workspace');

        $em->persist($workspace);

        $this->addUserOnWorkspace($ownerId, $workspace->getId());

        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $workspace;
    }

    protected function addUserOnWorkspace(string $ownerId, string $workspaceId): void
    {
        $permissionManager = self::getContainer()->get(PermissionManager::class);
        $permissionManager->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER,
            $ownerId,
            'workspace',
            $workspaceId,
            PermissionInterface::VIEW
        );
    }

    protected static function getPermissionManager(): PermissionManager
    {
        return self::getContainer()->get(PermissionManager::class);
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

    protected function findOrCreateTagByName(string $name): ?Tag
    {
        $em = self::getEntityManager();
        /** @var Tag $tag */
        $tag = $em->getRepository(Tag::class)->findOneBy([
            'workspace' => $this->defaultWorkspace,
            'name' => $name,
        ]);

        if (null === $tag) {
            $tag = new Tag();
            $tag->setWorkspace($this->defaultWorkspace);
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

    protected function getOrCreateDefaultAttributeClass(array $options = []): AttributeClass
    {
        if (null !== $this->defaultAttributeClass) {
            return $this->defaultAttributeClass;
        }

        return $this->defaultAttributeClass = $this->createAttributeClass(array_merge([
            'name' => 'Default',
        ], $options));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => 'cache:pool:clear',
            'pools' => ['doctrine.cache', 'memory.cache'],
        ]);
        $exitCode = $application->run($input, new NullOutput());
        if (0 !== $exitCode) {
            throw new \InvalidArgumentException(sprintf('Failed to clear pool cache'));
        }
    }

    protected function clearEmBeforeApiCall(): void
    {
        self::getEntityManager()->clear();
    }
}
