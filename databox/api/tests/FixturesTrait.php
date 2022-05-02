<?php

declare(strict_types=1);

namespace App\Tests;

use Hautelook\AliceBundle\PhpUnit\BaseDatabaseTrait;
use Symfony\Component\HttpKernel\KernelInterface;

trait FixturesTrait
{
    private static bool $withFixtures = false;
    use BaseDatabaseTrait;

    protected static function fixturesBootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }

        static::ensureKernelTestCase();
        parent::bootKernel($options);
        $container = static::$kernel->getContainer();

        $dbPath = $container->hasParameter('test_db_path')
            ? $container->getParameter('test_db_path')
            : null;
        $emptyDbPath = sprintf('%s/data-empty.cache.db', $container->getParameter('kernel.cache_dir'));

        if ($dbPath) {
            if (!file_exists($emptyDbPath)) {
                copy($dbPath, $emptyDbPath);
            }

            if (self::$withFixtures) {
                $cachePath = sprintf('%s/data.cache.db', $container->getParameter('kernel.cache_dir'));
                if (file_exists($cachePath)) {
                    copy($cachePath, $dbPath);
                } else {
                    static::populateDatabase();
                    copy($dbPath, $cachePath);
                }
                $container->get('doctrine')->getConnection()->executeQuery('PRAGMA foreign_keys = ON');
            } else {
                if (file_exists($emptyDbPath)) {
                    copy($emptyDbPath, $dbPath);
                }
            }

        } elseif (self::$withFixtures) {
            static::populateDatabase();
        }

        return static::$kernel;
    }

    public static function enableFixtures(): void
    {
        self::$withFixtures = true;
    }
}
