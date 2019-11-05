<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Alchemy\ApiTest\ApiTestCase;

abstract class AbstractTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function createPublication(): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $publication = new Publication();
        $publication->setLayout('gallery');
        $publication->setTitle('Foo');
        $em->persist($publication);
        $em->flush();

        return $publication->getId();
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
