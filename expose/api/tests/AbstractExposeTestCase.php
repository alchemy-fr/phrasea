<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\PublicationConfig;
use App\Entity\PublicationProfile;
use App\Entity\SubDefinition;
use App\Storage\FileStorageManager;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use InvalidArgumentException;

abstract class AbstractExposeTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function createPublication(array $options = []): string
    {
        $em = self::getEntityManager();

        $publication = new Publication();
        $this->configurePublication($publication, $options);

        $em->persist($publication);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $publication->getId();
    }

    protected function addAssetToPublication(string $publicationId, string $assetId, array $options = []): string
    {
        $em = self::getEntityManager();

        $publicationAsset = new PublicationAsset();
        $publicationAsset->setPublication($em->getReference(Publication::class, $publicationId));
        $publicationAsset->setAsset($em->getReference(Asset::class, $assetId));

        $em->persist($publicationAsset);
        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $publicationAsset->getId();
    }

    protected function configurePublication(Publication $publication, array $options): void
    {
        $options = array_merge([
            'enabled' => true,
            'publiclyListed' => true,
            'layout' => 'gallery',
        ], $options);

        $this->configureConfig($publication->getConfig(), $options);

        if (isset($options['ownerId'])) {
            $publication->setOwnerId($options['ownerId']);
        }
        if (isset($options['profile_id'])) {
            $publication->setProfile($this->findProfile($options['profile_id']));
        }
        if (isset($options['profile'])) {
            $publication->setProfile($options['profile']);
        }
        if (isset($options['parent_id'])) {
            /** @var Publication $parent */
            $parent = $this->findPublication($options['parent_id']);
            $parent->addChild($publication);
        }
        if (isset($options['parent'])) {
            $options['parent']->addChild($publication);
        }

        $publication->setTitle($options['title'] ?? 'Foo');
    }

    protected function configureConfig(PublicationConfig $config, array $options): void
    {
        if (isset($options['enabled'])) {
            $config->setEnabled($options['enabled']);
        }
        if (isset($options['publiclyListed'])) {
            $config->setPubliclyListed($options['publiclyListed']);
        }
        if (isset($options['css'])) {
            $config->setCss($options['css']);
        }
        if (isset($options['copyrightText'])) {
            $config->setCopyrightText($options['copyrightText']);
        }
        if (isset($options['password'])) {
            $config->setPassword($options['password']);
        }
        if (isset($options['layout'])) {
            $config->setLayout($options['layout']);
        }
        if (isset($options['mapOptions'])) {
            $config->setMapOptions($options['mapOptions']);
        }
        if (isset($options['startDate'])) {
            $config->setBeginsAt($options['startDate']);
        }
        if (isset($options['endDate'])) {
            $config->setExpiresAt($options['endDate']);
        }
    }

    protected function createProfile(array $options = []): string
    {
        $em = self::getEntityManager();

        $profile = new PublicationProfile();
        $this->configureProfile($profile, $options);

        $em->persist($profile);
        $em->flush();

        return $profile->getId();
    }

    protected function configureProfile(PublicationProfile $profile, array $options): void
    {
        $options = array_merge([
            'enabled' => true,
        ], $options);

        $config = $profile->getConfig();

        $this->configureConfig($config, $options);

        if (isset($options['ownerId'])) {
            $profile->setOwnerId($options['ownerId']);
        }

        $profile->setName($options['name'] ?? 'profile_foo');
    }

    private function findProfile(string $id): ?PublicationProfile
    {
        $em = self::getEntityManager();
        /** @var PublicationProfile $profile */
        $profile = $em->find(PublicationProfile::class, $id);

        return $profile;
    }

    private function findPublication(string $id): ?Publication
    {
        $em = self::getEntityManager();
        /** @var Publication $publication */
        $publication = $em->find(Publication::class, $id);

        return $publication;
    }

    private function findAsset(string $id): ?Asset
    {
        $em = self::getEntityManager();
        /** @var Asset $asset */
        $asset = $em->find(Asset::class, $id);

        return $asset;
    }

    protected function addPublicationChild($parentId, $childId): void
    {
        $em = self::getEntityManager();
        /** @var Publication $parent */
        $parent = $em->find(Publication::class, $parentId);
        /** @var Publication $child */
        $child = $em->find(Publication::class, $childId);

        $parent->addChild($child);
        $em->persist($parent);
        $em->flush();
    }

    protected function assertPublicationExists(string $id): void
    {
        $this->assertTrue($this->findPublication($id) instanceof Publication, 'Publication does not exist.');
    }

    protected function assertPublicationDoesNotExist(string $id): void
    {
        $this->assertTrue(null === $this->findPublication($id), 'Publication exists.');
    }

    protected function assertAssetExists(string $id): void
    {
        $this->assertTrue(null !== $this->findAsset($id), 'Asset does not exist.');
    }

    protected function assertAssetFileExists(string $path): void
    {
        $this->assertTrue(self::getStorageManager()->has($path), 'Asset file does not exist.');
    }

    protected function assertAssetFileDoesNotExist(string $path): void
    {
        $this->assertFalse(self::getStorageManager()->has($path), 'Asset file does exist.');
    }

    protected function assertAssetExist(string $id, bool $testStorage = false): Asset
    {
        /** @var Asset $obj */
        $obj = self::getEntityManager()->find(Asset::class, $id);
        $this->assertInstanceOf(Asset::class, $obj);

        if ($testStorage) {
            $this->assertAssetFileExists($obj->getPath());
        }

        return $obj;
    }

    protected function assertNotAssetExist(string $id): void
    {
        $obj = self::getEntityManager()->find(Asset::class, $id);
        $this->assertNull($obj, 'Asset exists.');
    }

    protected static function getStorageManager(): FileStorageManager
    {
        return self::$container->get(FileStorageManager::class);
    }

    protected function createAsset(array $options = []): string
    {
        $em = self::getEntityManager();

        $asset = new Asset();
        if (isset($options['description'])) {
            $asset->setDescription($options['description']);
        }
        $asset->setOriginalName('Foo.jpeg');
        $asset->setSize(42);
        $asset->setMimeType('image/jpeg');

        if (isset($options['asset_id'])) {
            $asset->setAssetId($options['asset_id']);
        }

        if (isset($options['publication_id'])) {
            $pubAsset = new PublicationAsset();
            $pubAsset->setAsset($asset);
            $publication = $em->find(Publication::class, $options['publication_id']);
            if (!$publication instanceof Publication) {
                throw new InvalidArgumentException('Publication not found');
            }
            $pubAsset->setPublication($publication);
            $em->persist($pubAsset);
        }

        $storageManager = self::getStorageManager();
        $path = $storageManager->generatePath('jpg');
        $storageManager->store($path, 'Dummy content');
        $asset->setPath($path);

        $em->persist($asset);

        if (!($options['no_flush'] ?? false)) {
            $em->flush();
        }

        return $asset->getId();
    }

    protected function createSubDefinition(string $assetId, array $options = []): string
    {
        $em = self::getEntityManager();

        $subDefinition = new SubDefinition();
        $subDefinition->setName($options['name'] ?? 'thumb');
        $subDefinition->setSize(42);
        $subDefinition->setPath('non-existing-file.jpeg');
        $subDefinition->setMimeType('image/jpeg');
        $subDefinition->setAsset($em->find(Asset::class, $assetId));

        $em->persist($subDefinition);
        $em->flush();

        return $subDefinition->getId();
    }

    protected function clearEmBeforeApiCall(): void
    {
        self::getEntityManager()->clear();
    }
}
