<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\PublicationProfile;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractExposeTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function createPublication(array $options = []): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $options = array_merge([
            'enabled' => true,
            'publicly_listed' => true,
        ], $options);

        $publication = new Publication();
        $config = $publication->getConfig();

        if (isset($options['parent_id'])) {
            /** @var Publication $parent */
            $parent = $em->find(Publication::class, $options['parent_id']);
            $parent->addChild($publication);
        }
        if (isset($options['enabled'])) {
            $config->setEnabled($options['enabled']);
        }
        if (isset($options['owner_id'])) {
            $publication->setOwnerId($options['owner_id']);
        }
        if (isset($options['publicly_listed'])) {
            $config->setPubliclyListed($options['publicly_listed']);
        }
        if (isset($options['password'])) {
            $config->setPassword($options['password']);
        }

        $config->setLayout('gallery');
        $publication->setTitle($options['title'] ?? 'Foo');
        $em->persist($publication);
        $em->flush();

        return $publication->getId();
    }

    protected function createProfile(array $options = []): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $options = array_merge([
            'enabled' => true,
        ], $options);

        $profile = new PublicationProfile();
        $config = $profile->getConfig();

        if (isset($options['enabled'])) {
            $config->setEnabled($options['enabled']);
        }
        if (isset($options['owner_id'])) {
            $profile->setOwnerId($options['owner_id']);
        }
        if (isset($options['publicly_listed'])) {
            $config->setPubliclyListed($options['publicly_listed']);
        }
        if (isset($options['password'])) {
            $config->setPassword($options['password']);
        }

        $config->setLayout('gallery');
        $profile->setName($options['name'] ?? 'profile_foo');
        $em->persist($profile);
        $em->flush();

        return $profile->getId();
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

    private function findPublication(string $id): ?Publication
    {
        $em = self::$container->get(EntityManagerInterface::class);
        /** @var Publication $publication */
        $publication = $em->find(Publication::class, $id);

        return $publication;
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
