<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Search\SearchTestTrait;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractSearchTestCase extends AbstractDataboxTestCase
{
    use SearchTestTrait;

    #[\Override]
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
