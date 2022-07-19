<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\TestBundle\Helper\FixturesTrait;
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Search\SearchTestTrait;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractSearchTestCase extends ApiTestCase
{
    use FixturesTrait;
    use SearchTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::bootKernelWithFixtures($options);
        self::bootSearch(static::$kernel);

        return static::$kernel;
    }
}
