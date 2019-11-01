<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Publication;
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
}
