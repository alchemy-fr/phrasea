<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use Alchemy\TestBundle\Helper\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @deprecated
 */
abstract class DeprecatedAbstractDataboxTestCase extends ApiTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => 'cache:pool:clear',
            'pools' => ['doctrine.cache', 'memory.cache'],
        ]);
        $exitCode = $application->run($input, new NullOutput());
        if (0 !== $exitCode) {
            throw new \InvalidArgumentException(sprintf('Failed to clear pool cache'));
        }
    }

    protected function clearEmBeforeApiCall(): void
    {
        self::getEntityManager()->clear();
    }
}
