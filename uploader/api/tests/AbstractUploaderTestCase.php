<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Target;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractUploaderTestCase extends ApiTestCase
{
    use FixturesTrait;
    use ApiTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::disableFixtures();
    }

    protected function getOrCreateDefaultTarget(): Target
    {
        $em = self::getEntityManager();

        $name = 'TestDefault';
        $slug = 'test-default';
        $target = $em->getRepository(Target::class)->findOneBy([
            'slug' => $slug,
        ]);

        if (!$target instanceof Target) {
            $target = new Target();
            $target->setName($name);
            $target->setSlug($slug);
            $target->setTargetUrl('http://localhost');

            $em->persist($target);
            $em->flush();
        }

        return $target;
    }
}
