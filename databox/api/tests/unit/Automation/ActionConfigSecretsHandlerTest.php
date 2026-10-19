<?php

declare(strict_types=1);

namespace App\Tests\Unit\Automation;

use App\Automation\Action\AbstractAutomationAction;
use App\Automation\ActionConfigSecretsHandler;
use App\Automation\AutomationActionRegistry;
use App\Automation\AutomationBatch;
use App\Security\Secrets\EncryptionManager;
use App\Security\Secrets\SecretsManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ActionConfigSecretsHandlerTest extends TestCase
{
    private const string TYPE = 'with_secrets';

    private function createHandler(bool $withSecretKey = true): ActionConfigSecretsHandler
    {
        $encryptionManager = new EncryptionManager();
        static $keyPair;
        $keyPair ??= $encryptionManager->generateKeyPair();

        $action = new class extends AbstractAutomationAction {
            public function validateConfig(array $config): array
            {
                return $config;
            }

            public function getSecretFields(): array
            {
                return ['password', 'credentials.token'];
            }

            public function processBatch(AutomationBatch $batch): void
            {
            }
        };

        return new ActionConfigSecretsHandler(
            new AutomationActionRegistry(new ServiceLocator([self::TYPE => fn () => $action])),
            new SecretsManager($encryptionManager, $withSecretKey ? $keyPair->getSecret() : '', $keyPair->getPublic()),
        );
    }

    public function testSecretsAreEncryptedMaskedAndDecrypted(): void
    {
        $handler = $this->createHandler();
        $config = [
            'host' => 'ftp.example.com',
            'password' => 's3cr3t',
            'credentials' => ['user' => 'bob', 'token' => 't0k3n'],
        ];

        $stored = $handler->encrypt(self::TYPE, $config);
        $this->assertSame('ftp.example.com', $stored['host']);
        $this->assertSame('bob', $stored['credentials']['user']);
        $this->assertNotSame('s3cr3t', $stored['password']);
        $this->assertNotSame('t0k3n', $stored['credentials']['token']);
        $this->assertStringNotContainsString('s3cr3t', json_encode($stored));

        $this->assertSame([
            'host' => 'ftp.example.com',
            'password' => null,
            'credentials' => ['user' => 'bob', 'token' => null, 'tokenSet' => true],
            'passwordSet' => true,
        ], $handler->mask(self::TYPE, $stored));

        $this->assertSame($config, $handler->decrypt(self::TYPE, $stored));
    }

    public function testEmptySecretKeepsThePreviousValue(): void
    {
        $handler = $this->createHandler();
        $stored = $handler->encrypt(self::TYPE, ['password' => 's3cr3t', 'credentials' => ['token' => 't0k3n']]);

        // As sent back by a client: the masked output with a changed value
        $updated = $handler->encrypt(self::TYPE, [
            'password' => null,
            'credentials' => ['token' => 'n3w'],
        ], $stored);

        $this->assertSame($stored['password'], $updated['password']);
        $this->assertSame(['password' => 's3cr3t', 'credentials' => ['token' => 'n3w']], $handler->decrypt(self::TYPE, $updated));

        // Without a previous value, an empty secret is just not set
        $this->assertSame(['credentials' => []], $handler->encrypt(self::TYPE, ['password' => '', 'credentials' => []]));
        $this->assertSame([
            'credentials' => ['token' => null, 'tokenSet' => false],
            'password' => null,
            'passwordSet' => false,
        ], $handler->mask(self::TYPE, ['credentials' => []]));
    }

    public function testUnknownTypeHasNoSecret(): void
    {
        $handler = $this->createHandler();
        $config = ['password' => 'plain'];

        $this->assertSame($config, $handler->encrypt('unknown', $config));
        $this->assertSame($config, $handler->mask('unknown', $config));
    }

    public function testSecretsCannotBeDecryptedWithoutTheSecretKey(): void
    {
        $stored = $this->createHandler()->encrypt(self::TYPE, ['password' => 's3cr3t']);

        $this->assertSame('***', $this->createHandler(false)->decrypt(self::TYPE, $stored)['password']);
    }
}
