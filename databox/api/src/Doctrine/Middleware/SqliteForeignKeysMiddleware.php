<?php

declare(strict_types=1);

namespace App\Doctrine\Middleware;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Driver\Middleware;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * The tests run on SQLite, which ignores foreign keys, and so their ON DELETE
 * CASCADE, unless asked otherwise: without it, what PostgreSQL cascades is left
 * behind (the ORM leaves those deletions to the database).
 */
#[When(env: 'test')]
#[AsMiddleware(connections: ['default'])]
final readonly class SqliteForeignKeysMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new EnableForeignKeys()->wrap($driver);
    }
}
