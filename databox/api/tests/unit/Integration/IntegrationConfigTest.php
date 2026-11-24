<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceEnv;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Integration\WorkspaceSecret;
use App\Integration\Env\EnvResolver;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationInterface;
use App\Security\Secrets\EncryptionManager;
use App\Security\Secrets\SecretKeyUnavailableException;
use App\Security\Secrets\SecretsManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class IntegrationConfigTest extends TestCase
{
    private const array INSTANCE_VARIABLES = [
        'PWI_UT_CFG_KEY' => 'instance-key',
        'PWI_UT_CFG_URL' => 'https://service.instance.test',
        'PWI_UT_CFG_CLIENT' => 'instance-client',
    ];

    private const array URL_DEFAULTS = [
        'baseUrl' => '${UT_CFG_URL}',
        'hookUrl' => null,
    ];

    private const array DEFAULTS = [
        'baseUrl' => '${UT_CFG_URL}',
        'clientId' => '${UT_CFG_CLIENT}',
    ];

    private const array SECRET_KEYS = ['apiKey', 'webhookKey'];

    private const array API_READ_KEYS = ['webhookKey'];

    /**
     * @var array<string, string> the variables of the workspace
     */
    private array $workspaceEnvs = [];

    /**
     * @var list<string> the names of the secrets of the workspace
     */
    private array $workspaceSecrets = [];

    /**
     * @var list<string>
     */
    private array $warnings = [];

    protected function setUp(): void
    {
        foreach (self::INSTANCE_VARIABLES as $name => $value) {
            putenv($name.'='.$value);
        }
        $this->workspaceEnvs = [];
        $this->workspaceSecrets = [];
        $this->warnings = [];
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::INSTANCE_VARIABLES) as $name) {
            putenv($name);
        }
    }

    public function testTheDefaultUrlOfAWorkspaceIntegrationResolvesTheInstanceVariables(): void
    {
        $config = $this->createConfig([
            'baseUrl' => '${UT_CFG_URL}',
            'apiKey' => '${UT_CFG_KEY}',
        ]);

        $this->assertSame('https://service.instance.test', $config['baseUrl']);
        $this->assertSame('instance-key', $config['apiKey']);
        $config->validateReferences();
        $this->assertSame([], $this->warnings);
    }

    public function testAUrlSetByAWorkspaceEditorDisablesTheInstanceVariables(): void
    {
        $this->workspaceEnvs['UT_CFG_OWN'] = 'own-value';
        $config = $this->createConfig([
            'baseUrl' => 'https://attacker.test/collect',
            'apiKey' => '${UT_CFG_KEY}',
            'other' => 'own:$UT_CFG_OWN',
        ]);

        $this->assertSame('https://attacker.test/collect', $config['baseUrl']);
        $this->assertSame('', $config['apiKey']);
        // The values of the workspace still resolve
        $this->assertSame('own:own-value', $config['other']);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('PWI_UT_CFG_KEY', $this->warnings[0]);
    }

    public function testAUrlWithoutDefaultIsSetByTheEditor(): void
    {
        $config = $this->createConfig([
            'baseUrl' => '${UT_CFG_URL}',
            'hookUrl' => 'https://attacker.test/hook',
            'apiKey' => '${UT_CFG_KEY}',
        ]);

        $this->assertSame('', $config['apiKey']);
        $this->assertSame('https://service.instance.test', $config['baseUrl']);
    }

    public function testAUrlSetByAWorkspaceEditorIsNotResolved(): void
    {
        $this->workspaceEnvs['UT_CFG_HOST'] = 'attacker.test';
        $config = $this->createConfig([
            'baseUrl' => 'https://${UT_CFG_HOST}/?k=$UT_CFG_KEY',
        ]);

        $this->assertSame('https://${UT_CFG_HOST}/?k=$UT_CFG_KEY', $config['baseUrl']);

        try {
            $config->validateReferences();
            $this->fail('A reference in a URL must be refused');
        } catch (InvalidConfigurationException $e) {
            $this->assertSame('root.baseUrl', $e->getPath());
        }
    }

    public function testADefaultUrlRedirectedByAWorkspaceVariableDisablesTheInstanceVariables(): void
    {
        $this->workspaceEnvs['UT_CFG_URL'] = 'https://attacker.test';
        $config = $this->createConfig([
            'baseUrl' => '${UT_CFG_URL}',
            'apiKey' => '${UT_CFG_KEY}',
        ]);

        $this->assertSame('https://attacker.test', $config['baseUrl']);
        $this->assertSame('', $config['apiKey']);
        $config->validateReferences();
    }

    public function testAResolvedValueIsNotResolvedAgain(): void
    {
        $this->workspaceEnvs['UT_CFG_URL'] = 'https://attacker.test/?k=$UT_CFG_KEY';
        $config = $this->createConfig([
            'baseUrl' => '${UT_CFG_URL}',
        ]);

        $this->assertSame('https://attacker.test/?k=$UT_CFG_KEY', $config['baseUrl']);
    }

    public function testAnInstanceIntegrationResolvesEverything(): void
    {
        $this->workspaceEnvs['UT_CFG_HOST'] = 'service.instance.test';
        $config = $this->createConfig([
            'baseUrl' => 'https://${UT_CFG_HOST}',
            'apiKey' => '${UT_CFG_KEY}',
            'label' => '${UT_CFG_KEY}',
        ], workspace: false);

        $this->assertSame('https://service.instance.test', $config['baseUrl']);
        $this->assertSame('instance-key', $config['apiKey']);
        $this->assertSame('instance-key', $config['label']);
        $config->validateReferences();
        $this->assertSame([], $this->warnings);
    }

    public function testAWorkspaceIntegrationResolvesTheInstanceVariablesInItsSecretKeysOnly(): void
    {
        $this->workspaceEnvs['UT_CFG_OWN'] = 'own-value';
        $config = $this->createConfig([
            'apiKey' => '${UT_CFG_KEY}',
            'label' => 'Key: ${UT_CFG_KEY}',
            'other' => '${UT_CFG_OWN}',
        ]);

        $this->assertSame('instance-key', $config['apiKey']);
        $this->assertSame('Key: ', $config['label']);
        // The values of the workspace still resolve
        $this->assertSame('own-value', $config['other']);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('PWI_UT_CFG_KEY', $this->warnings[0]);
    }

    public function testAKeyLeftToItsDefaultResolvesTheInstanceVariables(): void
    {
        $this->assertSame('instance-client', $this->createConfig(['clientId' => '${UT_CFG_CLIENT}'])['clientId']);
        $this->assertSame('', $this->createConfig(['clientId' => '${UT_CFG_KEY}'])['clientId']);
    }

    public function testTheClientValueIsTheValueAsWritten(): void
    {
        $config = $this->createConfig([
            'apiKey' => '${UT_CFG_KEY}',
            'label' => 'plain',
        ]);

        $this->assertSame('instance-key', $config['apiKey']);
        $this->assertSame('${UT_CFG_KEY}', $config->getClientValue('apiKey'));
        $this->assertSame('plain', $config->getClientValue('label'));
        $this->assertNull($config->getClientValue('missing'));
    }

    public function testASecretCannotBeResolvedWithoutTheSecretKey(): void
    {
        $this->workspaceSecrets[] = 'UT_CFG_SECRET';
        $config = $this->createConfig([
            'apiKey' => '${UT_CFG_SECRET}',
            'label' => 'plain',
        ]);

        $this->assertSame('plain', $config['label']);
        $this->expectException(SecretKeyUnavailableException::class);
        $config->offsetGet('apiKey');
    }

    public function testAKeyReadByTheApiCannotReferToASecret(): void
    {
        $this->workspaceSecrets[] = 'UT_CFG_SECRET';
        $this->workspaceEnvs['UT_CFG_OWN'] = 'own-value';

        $this->createConfig(['webhookKey' => '${UT_CFG_OWN}'])->validateReferences();
        $this->createConfig(['webhookKey' => 'plain', 'apiKey' => '${UT_CFG_SECRET}'])->validateReferences();

        foreach (['${UT_CFG_SECRET}', 'k-$UT_CFG_SECRET'] as $value) {
            try {
                $this->createConfig(['webhookKey' => $value])->validateReferences();
                $this->fail('A reference to a secret in a key read by the API must be refused');
            } catch (InvalidConfigurationException $e) {
                $this->assertSame('root.webhookKey', $e->getPath());
                $this->assertStringContainsString('UT_CFG_SECRET', $e->getMessage());
            }
        }
    }

    private function createConfig(array $values, bool $workspace = true): IntegrationConfig
    {
        $workspaceIntegration = new WorkspaceIntegration();
        if ($workspace) {
            $workspaceIntegration->setWorkspace(new Workspace());
        }

        return new IntegrationConfig(
            $values,
            $workspaceIntegration,
            $this->createStub(IntegrationInterface::class),
            $this->createEnvResolver(),
            self::URL_DEFAULTS,
            self::DEFAULTS,
            self::SECRET_KEYS,
            self::API_READ_KEYS,
        );
    }

    private function createEnvResolver(): EnvResolver
    {
        $secrets = $this->createStub(EntityRepository::class);
        $secrets->method('findOneBy')->willReturnCallback(function (array $criteria): ?WorkspaceSecret {
            if (!in_array($criteria['name'], $this->workspaceSecrets, true)) {
                return null;
            }

            $secret = new WorkspaceSecret();
            $secret->setName($criteria['name']);
            $secret->setValue('encrypted');

            return $secret;
        });

        $envs = $this->createStub(EntityRepository::class);
        $envs->method('findOneBy')->willReturnCallback(function (array $criteria): ?WorkspaceEnv {
            if (!isset($this->workspaceEnvs[$criteria['name']])) {
                return null;
            }

            $env = new WorkspaceEnv();
            $env->setName($criteria['name']);
            $env->setValue($this->workspaceEnvs[$criteria['name']]);

            return $env;
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn (string $class): EntityRepository => match ($class) {
            WorkspaceSecret::class => $secrets,
            WorkspaceEnv::class => $envs,
        });

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function (string|\Stringable $message): void {
            $this->warnings[] = (string) $message;
        });

        return new EnvResolver(new SecretsManager(new EncryptionManager(), '', ''), $em, $logger);
    }
}
