<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use App\Integration\IntegrationManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A `${NAME}` reference of an integration configuration may resolve to a `PWI_<NAME>`
 * variable of the instance, shared by every workspace: an editor of a workspace
 * integration never gets its value back, neither from the API nor through a key whose
 * value they read.
 */
final class IntegrationReferencesTest extends AbstractDataboxTestCase
{
    use IntegrationTestTrait;

    private const string INSTANCE_VARIABLE = 'PWI_UT_REF_LEAK';
    private const string INSTANCE_VALUE = 'instance-only-0f3c9e7a';
    private const string REFERENCE = '${UT_REF_LEAK}';

    protected function setUp(): void
    {
        parent::setUp();
        putenv(self::INSTANCE_VARIABLE.'='.self::INSTANCE_VALUE);
    }

    protected function tearDown(): void
    {
        putenv(self::INSTANCE_VARIABLE);
        parent::tearDown();
    }

    public static function getConfigInfoReferences(): iterable
    {
        yield 'uploader security key' => ['phrasea.uploader', ['securityKey' => self::REFERENCE], 'Security Key', self::REFERENCE];
        yield 'asset import security key' => ['core.asset_import', ['securityKey' => self::REFERENCE], 'Security Key', self::REFERENCE];
        yield 'phraseanet webhook secret' => ['phraseanet.renditions', [
            'method' => 'enqueue',
            'collectionId' => 1,
            'renditions' => ['preview'],
            'webhookSecret' => self::REFERENCE,
        ], 'Webhook URL', '?token='.self::REFERENCE];
    }

    #[DataProvider('getConfigInfoReferences')]
    public function testTheConfigurationInfoShowsTheReferencesAsWritten(string $type, array $config, string $label, string $expected): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, $type, $config);

        $response = static::createClient()->request('GET', '/integrations/'.$integration->getId(), [
            'headers' => $this->headers(self::OWNER),
        ]);

        $this->assertResponseIsSuccessful();
        $info = array_column($response->toArray()['configInfo'], 'value', 'label');
        $this->assertStringEndsWith($expected, $info[$label]);
        $this->assertStringNotContainsString(self::INSTANCE_VALUE, $response->getContent());
        // The integration itself still uses the credential of the instance
        $this->assertSame(self::INSTANCE_VALUE, $this->getResolvedConfig($integration->getId())[array_key_last($config)]);
    }

    public function testTheClientConfigurationOfFaceRecognitionHoldsNoReference(): void
    {
        $workspace = $this->createSharedWorkspace();
        $integration = $this->createIntegration($workspace, 'core.face_recognition', [
            'attribute' => self::REFERENCE,
        ], public: true);

        foreach ([self::OWNER, self::MEMBER] as $userId) {
            $response = static::createClient()->request('GET', '/integrations/'.$integration->getId(), [
                'headers' => $this->headers($userId),
            ]);

            $this->assertResponseIsSuccessful();
            $this->assertNull($response->toArray()['config']['attribute'] ?? null);
            $this->assertStringNotContainsString(self::INSTANCE_VALUE, $response->getContent());
        }
    }

    public function testAValidationMessageShowsTheReferenceAsWritten(): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = static::createClient()->request('POST', '/integrations', [
            'headers' => $this->headers(self::OWNER),
            'json' => [
                'integration' => 'core.face_recognition',
                'workspace' => '/workspaces/'.$workspace->getId(),
                'public' => false,
                'config' => ['attribute' => self::REFERENCE],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $violations = $response->toArray(false)['violations'];
        $this->assertSame(['config.attribute'], array_column($violations, 'propertyPath'));
        $this->assertStringContainsString(self::REFERENCE, $violations[0]['message']);
        $this->assertStringNotContainsString(self::INSTANCE_VALUE, $response->getContent(false));
    }

    public function testAnInstanceVariableOnlyResolvesInASecretKeyOrADefaultValue(): void
    {
        $workspace = $this->createSharedWorkspace();
        $faces = $this->createIntegration($workspace, 'core.face_recognition', [
            'attribute' => 'faces',
            'unknownLabel' => self::REFERENCE,
        ]);
        $removeBg = $this->createIntegration($workspace, 'remove.bg', ['apiKey' => self::REFERENCE]);
        $expose = $this->createIntegration($workspace, 'phrasea.expose', [
            'clientId' => self::REFERENCE,
        ]);

        // The name of the unknown persons is written to the entities read by the users
        $this->assertSame('', $this->getResolvedConfig($faces->getId())['unknownLabel']);
        // A credential is never shown
        $this->assertSame(self::INSTANCE_VALUE, $this->getResolvedConfig($removeBg->getId())['apiKey']);
        // The client ID is part of the URL users are redirected to
        $this->assertSame('', $this->getResolvedConfig($expose->getId())['clientId']);
    }

    public function testAnInstanceVariableResolvesInAKeyLeftToItsDefault(): void
    {
        putenv('PWI_DATABOX_EXPOSE_INTEGRATION_CLIENT_ID=expose-integration-ut');
        try {
            $workspace = $this->createSharedWorkspace();
            $default = $this->createIntegration($workspace, 'phrasea.expose');
            $explicit = $this->createIntegration($workspace, 'phrasea.expose', [
                'clientId' => '${DATABOX_EXPOSE_INTEGRATION_CLIENT_ID}',
            ]);

            $this->assertSame('expose-integration-ut', $this->getResolvedConfig($default->getId())['clientId']);
            $this->assertSame('expose-integration-ut', $this->getResolvedConfig($explicit->getId())['clientId']);
        } finally {
            putenv('PWI_DATABOX_EXPOSE_INTEGRATION_CLIENT_ID');
        }
    }

    public function testAnInstanceWideIntegrationResolvesEveryKey(): void
    {
        $integration = $this->createIntegration(null, 'core.face_recognition', [
            'attribute' => 'faces',
            'unknownLabel' => self::REFERENCE,
        ]);

        $this->assertSame(self::INSTANCE_VALUE, $this->getResolvedConfig($integration->getId())['unknownLabel']);
    }

    private function getResolvedConfig(string $integrationId): \ArrayAccess
    {
        $manager = self::getService(IntegrationManager::class);

        return $manager->getIntegrationConfiguration($manager->loadIntegration($integrationId));
    }
}
