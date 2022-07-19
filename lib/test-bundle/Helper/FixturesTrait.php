<?php

declare(strict_types=1);

namespace Alchemy\TestBundle\Helper;

use Hautelook\AliceBundle\PhpUnit\BaseDatabaseTrait;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * This trait does not provide bootKernel on purpose because you may need to add custom logic or combine other traits.
 * So you need to add this to your (abstract) test class:
 *
 *  protected static function bootKernel(array $options = []): KernelInterface
 *  {
 *      return static::bootKernelWithFixtures($options);
 *  }
 */
trait FixturesTrait
{
    use BaseDatabaseTrait;

    private static bool $withFixtures = false;

    protected static function bootKernelWithFixtures(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }

        static::ensureKernelTestCase();
        parent::bootKernel($options);
        $container = static::$kernel->getContainer();

        $dbPath = $container->getParameter('alchemy_test.db_path');
        $dbFilledPath = $container->getParameter('alchemy_test.db_filled_path');
        $dbEmptyPath = $container->getParameter('alchemy_test.db_empty_path');

        if ($dbPath) {
            if (!file_exists($dbEmptyPath)) {
                copy($dbPath, $dbEmptyPath);
            }

            if (self::$withFixtures) {
                if (file_exists($dbFilledPath)) {
                    copy($dbFilledPath, $dbPath);
                } else {
                    static::populateDatabase();
                    copy($dbPath, $dbFilledPath);
                }
                $container->get('doctrine')->getConnection()->executeQuery('PRAGMA foreign_keys = ON');
            } else {
                if (file_exists($dbEmptyPath)) {
                    copy($dbEmptyPath, $dbPath);
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
