<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractDataboxTestCase extends ApiTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::disableFixtures();
        $this->releaseTestProperties();
    }

    /**
     * PHPUnit 9 keeps every test case instance until the end of the run. An entity
     * kept in a property (e.g. $defaultWorkspace) holds its EntityManager, hence the
     * whole container of that test: release them so the suite stays under 1G.
     */
    private function releaseTestProperties(): void
    {
        foreach ((new \ReflectionObject($this))->getProperties() as $property) {
            if ($property->isStatic() || !str_starts_with($property->getDeclaringClass()->getName(), 'App\\Tests\\')) {
                continue;
            }

            $type = $property->getType();
            if (null === $type || $type->allowsNull()) {
                $property->setValue($this, null);
            } elseif (!$property->isReadOnly()) {
                // A typed non-nullable property can only be reset by unsetting it, from its declaring scope
                $name = $property->getName();
                \Closure::bind(function () use ($name): void {
                    unset($this->{$name});
                }, $this, $property->getDeclaringClass()->getName())();
            }
        }
    }
}
