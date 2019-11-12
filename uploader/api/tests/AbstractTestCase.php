<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function createPublication(): string
    {
        $em = self::$container->get(EntityManagerInterface::class);

        $publication = new Publication();
        $publication->setLayout('gallery');
        $publication->setName('Foo');
        $em->persist($publication);
        $em->flush();

        return $publication->getId();
    }
}
