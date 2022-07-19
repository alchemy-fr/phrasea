<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Tests\AbstractDataboxTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractSearchTest extends AbstractDataboxTestCase
{
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
