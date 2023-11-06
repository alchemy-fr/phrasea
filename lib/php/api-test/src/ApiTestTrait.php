<?php

declare(strict_types=1);

namespace Alchemy\ApiTest;

use Doctrine\ORM\EntityManagerInterface;

trait ApiTestTrait
{
    final public const UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    protected function assertMatchesUuid($uuid): void
    {
        $this->assertMatchesRegularExpression('#^'.self::UUID_REGEX.'$#', $uuid);
    }

    /**
     * @template T
     *
     * @param class-string<T> $name
     *
     * @return T
     */
    protected static function getService(string $name): object
    {
        return static::getContainer()->get($name);
    }

    protected static function getEntityManager(): EntityManagerInterface
    {
        return self::getService(EntityManagerInterface::class);
    }
}
