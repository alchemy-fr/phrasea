<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractDataboxTestCase extends ApiTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::bootKernelWithFixtures($options);

        return static::$kernel;
    }
}
