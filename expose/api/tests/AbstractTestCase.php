<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function createPublication(array $options = []): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $options = array_merge([
            'enabled' => true,
        ], $options);

        $publication = new Publication();
        if (isset($options['parent_id'])) {
            $publication->setRoot(false);
            /** @var Publication $parent */
            $parent = $em->find(Publication::class, $options['parent_id']);
            $parent->addChild($publication);
            $em->persist($parent);
        }
        if (isset($options['enabled'])) {
            $publication->setEnabled($options['enabled']);
        }
        if (isset($options['owner_id'])) {
            $publication->setOwnerId($options['owner_id']);
        }

        $publication->setLayout('gallery');
        $publication->setTitle('Foo');
        $em->persist($publication);
        $em->flush();

        return $publication->getId();
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

    protected function createAsset(?string $publicationId): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $asset = new Asset();
        $asset->setOriginalName('Foo.jpeg');
        $asset->setSize(42);
        $asset->setPath('/non-existing-file.jpeg');
        $asset->setMimeType('image/jpeg');

        if (null !== $publicationId) {
            $pubAsset = new PublicationAsset();
            $pubAsset->setAsset($asset);
            $pubAsset->setPublication($em->find(Publication::class, $publicationId));
            $em->persist($pubAsset);
        }

        $em->persist($asset);
        $em->flush();

        return $asset->getId();
    }
}
