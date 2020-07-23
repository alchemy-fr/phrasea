<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\PublicationConfig;
use App\Entity\PublicationProfile;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractExposeTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function createPublication(array $options = []): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $publication = new Publication();
        $this->configurePublication($publication, $options);

        $em->persist($publication);
        $em->flush();

        return $publication->getId();
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
    }

    protected function createProfile(array $options = []): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

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
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var PublicationProfile $profile */
        $profile = $em->find(PublicationProfile::class, $id);

        return $profile;
    }

    private function findPublication(string $id): ?Publication
    {
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var Publication $publication */
        $publication = $em->find(Publication::class, $id);

        return $publication;
    }

    protected function addPublicationChild($parentId, $childId): void
    {
        $em = self::$container->get(EntityManagerInterface::class);
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

    protected function createAsset(array $options = []): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $asset = new Asset();
        if (isset($options['description'])) {
            $asset->setDescription($options['description']);
        }
        $asset->setOriginalName('Foo.jpeg');
        $asset->setSize(42);
        $asset->setPath('non-existing-file.jpeg');
        $asset->setMimeType('image/jpeg');

        if (isset($options['asset_id'])) {
            $asset->setAssetId($options['asset_id']);
        }

        if (isset($options['publication_id'])) {
            $pubAsset = new PublicationAsset();
            $pubAsset->setAsset($asset);
            $pubAsset->setPublication($em->find(Publication::class, $options['publication_id']));
            $em->persist($pubAsset);
        }

        $em->persist($asset);
        $em->flush();

        return $asset->getId();
    }
}
