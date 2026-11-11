<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTestCase;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Restores the SQLite database at each boot, like the other functional tests: the admin
 * tests run first, and their writes would otherwise end up in the "empty" database
 * snapshot that every following test starts from.
 */
abstract class AbstractDataboxAdminTestCase extends AbstractAdminTestCase
{
    use FixturesTrait;

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }
}
