<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceSecret;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\After;

/**
 * Runs a test as the API container, which only holds the public key of the secrets:
 * SecretsManager then cannot decrypt them (only the workers can).
 */
trait WithoutSecretKeyTrait
{
    private const string SECRET_KEY_ENV = 'TEST_SECRETS_SECRET_KEY';

    private static ?string $savedSecretKey = null;

    /**
     * Reboots the kernel without the secret key, on a fresh database: call it first.
     */
    private static function removeSecretKey(): void
    {
        self::$savedSecretKey ??= (string) ($_ENV[self::SECRET_KEY_ENV] ?? '');
        $_ENV[self::SECRET_KEY_ENV] = $_SERVER[self::SECRET_KEY_ENV] = '';
        self::ensureKernelShutdown();
        // FixturesTrait boots a new kernel only when there is none
        static::$kernel = null;
        static::bootKernel();
    }

    private static function createWorkspaceSecret(Workspace $workspace, string $name, string $value): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $secret = new WorkspaceSecret();
        $secret->setWorkspace($em->find(Workspace::class, $workspace->getId()));
        $secret->setName($name);
        $secret->setPlainValue($value);
        $em->persist($secret);
        $em->flush();
    }

    #[After]
    protected function restoreSecretKey(): void
    {
        if (null === self::$savedSecretKey) {
            return;
        }

        $_ENV[self::SECRET_KEY_ENV] = $_SERVER[self::SECRET_KEY_ENV] = self::$savedSecretKey;
        self::$savedSecretKey = null;
    }
}
