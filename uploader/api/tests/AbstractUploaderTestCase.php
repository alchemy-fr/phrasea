<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Target;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractUploaderTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected function getOrCreateDefaultTarget(): Target
    {
        $em = self::getEntityManager();

        $name = 'TestDefault';
        $target = $em->getRepository(Target::class)->findOneBy([
            'name' => $name,
        ]);

        if (!$target instanceof Target) {
            $target = new Target();
            $target->setName($name);
            $target->setTargetUrl('http://localhost');

            $em->persist($target);
            $em->flush();
        }

        return $target;
    }
}
