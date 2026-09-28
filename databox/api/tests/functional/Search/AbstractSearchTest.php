<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractSearchTest extends AbstractDataboxTestCase
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
