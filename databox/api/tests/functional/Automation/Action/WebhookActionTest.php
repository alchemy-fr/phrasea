<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\TextAttributeType;
use App\Automation\Action\WebhookAction;
use App\Automation\ActionConfigSecretsHandler;
use App\Automation\AutomationActionRegistry;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Security\ClientUrlHelper;
use App\Security\Secrets\EncryptionManager;
use App\Security\Secrets\SecretsManager;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WebhookActionTest extends AbstractDataboxTestCase
{
    use AutomationActionTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    // A public address: the destination is never actually called
    private const string URL = 'https://93.184.215.14/hooks/phrasea';

    private Workspace $workspace;

    /**
     * @var list<array{method: string, url: string, headers: array<string, string>, body: string}>
     */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->requests = [];
    }

    /**
     * @param int[] $statusCodes the status codes of the successive responses
     */
    private function createAction(array $statusCodes = [200], string $kernelEnv = 'test'): WebhookAction
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$statusCodes): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $header) {
                [$name, $value] = explode(': ', $header, 2);
                $headers[strtolower($name)] = $value;
            }
            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $options['body'],
            ];

            return new MockResponse('', ['http_code' => array_shift($statusCodes) ?? 200]);
        });

        return new WebhookAction(
            $client,
            self::getService(ClientUrlHelper::class),
            self::getService(AssetNameResolver::class),
            self::getService(AttributesResolver::class),
            self::getService(AttributeTypeRegistry::class),
            $kernelEnv,
        );
    }

    private function createOwnedAsset(array $options = []): Asset
    {
        return $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::OWNER,
        ] + $options);
    }

    public function testPerBatchSignedCall(): void
    {
        $action = $this->createAction();
        $assets = [$this->createOwnedAsset(), $this->createOwnedAsset()];

        $state = $this->processAction($action, [
            'url' => self::URL,
            'method' => 'put',
            'headers' => ['Authorization' => 'Bearer abc'],
            'secret' => 's3cr3t',
        ], $assets);

        $this->assertSame(2, $state->getProcessed(), json_encode($state->getErrors()));
        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $this->assertSame('PUT', $request['method']);
        $this->assertSame(self::URL, $request['url']);
        $this->assertSame('Bearer abc', $request['headers']['authorization']);
        $this->assertSame('application/json', $request['headers']['content-type']);
        $this->assertSame(WebhookAction::EVENT_BATCH, $request['headers']['x-phrasea-event']);
        $this->assertSame('sha256='.hash_hmac('sha256', $request['body'], 's3cr3t'), $request['headers']['x-phrasea-signature']);

        $payload = json_decode($request['body'], true);
        $this->assertSame(WebhookAction::EVENT_BATCH, $payload['event']);
        $this->assertSame(['id' => 'automation', 'name' => 'Test'], $payload['automation']);
        $this->assertSame(['id' => 'search', 'name' => 'Search'], $payload['savedSearch']);
        $this->assertSame(['id' => 'run', 'trigger' => 'manual'], $payload['run']);
        $this->assertSame(1, $payload['part']);
        $this->assertSame([$assets[0]->getId(), $assets[1]->getId()], array_column($payload['assets'], 'id'));
        $first = $payload['assets'][0];
        $this->assertSame($this->workspace->getId(), $first['workspaceId']);
        $this->assertStringEndsWith('/assets/'.$assets[0]->getId().'/_', $first['url']);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayNotHasKey('attributes', $first);
    }

    public function testPerBatchWithoutSecretIsNotSigned(): void
    {
        $action = $this->createAction();
        $this->processAction($action, ['url' => self::URL], [$this->createOwnedAsset()]);

        $this->assertCount(1, $this->requests);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertArrayNotHasKey('x-phrasea-signature', $this->requests[0]['headers']);
    }

    public function testFailedBatchFailsItsAssets(): void
    {
        $action = $this->createAction([500, 204]);
        $config = ['url' => self::URL];
        $state = new AutomationActionState(WebhookAction::TYPE);

        $failed = $this->createOwnedAsset();
        $succeeded = $this->createOwnedAsset();
        $this->processAction($action, $config, [$failed], state: $state);
        $this->processAction($action, $config, [$succeeded], state: $state);

        $this->assertCount(2, $this->requests);
        $this->assertSame(2, json_decode($this->requests[1]['body'], true)['part']);
        $this->assertSame(1, $state->getProcessed());
        $this->assertSame(1, $state->getFailed());
        $this->assertSame([['assetId' => $failed->getId(), 'message' => 'The webhook answered with HTTP status 500']], $state->getErrors());
        $this->assertSame(AutomationActionState::STATUS_PARTIAL, $state->getStatus());
    }

    public function testSummaryIsSentOnceAtTheEnd(): void
    {
        $action = $this->createAction();
        $config = ['url' => self::URL, 'mode' => WebhookAction::MODE_SUMMARY, 'secret' => 'key'];
        $state = new AutomationActionState(WebhookAction::TYPE);

        $first = [$this->createOwnedAsset(), $this->createOwnedAsset()];
        $second = [$this->createOwnedAsset()];
        $this->processAction($action, $config, $first, state: $state);
        $this->processAction($action, $config, $second, state: $state);

        $this->assertSame([], $this->requests);
        $this->assertSame(3, $state->getProcessed());

        $action->finish($state, $this->validateActionConfig($action, $config), self::createRunContext());

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $this->assertSame(WebhookAction::EVENT_SUMMARY, $request['headers']['x-phrasea-event']);
        $this->assertSame(WebhookAction::sign($request['body'], 'key'), $request['headers']['x-phrasea-signature']);
        $payload = json_decode($request['body'], true);
        $this->assertSame(WebhookAction::EVENT_SUMMARY, $payload['event']);
        $this->assertSame(3, $payload['total']);
        $this->assertFalse($payload['truncated']);
        $this->assertSame(
            array_map(fn (Asset $asset): string => $asset->getId(), array_merge($first, $second)),
            array_column($payload['assets'], 'id'),
        );
    }

    public function testEmptySummaryIsNotSent(): void
    {
        $action = $this->createAction();
        $config = $this->validateActionConfig($action, ['url' => self::URL, 'mode' => WebhookAction::MODE_SUMMARY]);

        $action->finish(new AutomationActionState(WebhookAction::TYPE), $config, self::createRunContext());

        $this->assertSame([], $this->requests);
    }

    public function testFailedSummaryThrows(): void
    {
        $action = $this->createAction([502]);
        $config = ['url' => self::URL, 'mode' => WebhookAction::MODE_SUMMARY];
        $state = $this->processAction($action, $config, [$this->createOwnedAsset()]);

        $this->expectExceptionMessage('The webhook answered with HTTP status 502');
        $action->finish($state, $this->validateActionConfig($action, $config), self::createRunContext());
    }

    public function testIncludeAttributes(): void
    {
        $definition = $this->createAttributeDefinition([
            'workspace' => $this->workspace,
            'name' => 'Status',
            'slug' => 'status',
            'type' => TextAttributeType::NAME,
        ]);
        $asset = $this->createOwnedAsset([
            'attributes' => [['definition' => $definition, 'value' => 'Published']],
        ]);

        $action = $this->createAction();
        $this->processAction($action, ['url' => self::URL, 'includeAttributes' => true], [$asset]);

        $payload = json_decode($this->requests[0]['body'], true);
        $this->assertSame(
            ['status' => [AttributeInterface::NO_LOCALE => 'Published']],
            $payload['assets'][0]['attributes'],
        );
    }

    public function testPrivateHostsAreBlockedWhenCalling(): void
    {
        $action = $this->createAction();
        $asset = $this->createOwnedAsset();

        // Valid when written, the host may resolve to a private address since
        $state = new AutomationActionState(WebhookAction::TYPE);
        $state->beginBatch();
        $action->processBatch(new AutomationBatch(
            [self::getEntityManager()->find(Asset::class, $asset->getId())],
            ['url' => 'http://127.0.0.1:8080/hook', 'method' => 'POST', 'headers' => [], 'mode' => WebhookAction::MODE_PER_BATCH],
            $state,
            self::createRunContext(),
        ));

        $this->assertSame([], $this->requests);
        $this->assertSame(1, $state->getFailed());
        $this->assertStringContainsString('is blocked', $state->getErrors()[0]['message']);
    }

    public function testUrlValidationRefusesPrivateHosts(): void
    {
        $action = $this->createAction();

        foreach ([
            'ftp://93.184.215.14/hook',
            'file:///etc/passwd',
            '/relative/hook',
            'http://localhost/hook',
            'http://api.localhost:8080/hook',
            'http://127.0.0.1/hook',
            'http://10.1.2.3/hook',
            'http://192.168.1.10/hook',
            'http://169.254.169.254/latest/meta-data',
            'http://[::1]/hook',
        ] as $url) {
            try {
                $action->validateConfig(['url' => $url]);
                $this->fail(sprintf('Expected a validation error for "%s"', $url));
            } catch (ValidationException $e) {
                $this->assertSame('url', $e->getConstraintViolationList()->get(0)->getPropertyPath(), $url);
            }
        }

        // Allowed in the dev environment only
        $this->assertSame('http://127.0.0.1/hook', $this->createAction(kernelEnv: 'dev')->validateConfig(['url' => 'http://127.0.0.1/hook'])['url']);
    }

    public function testValidation(): void
    {
        $action = $this->createAction();

        $this->assertSame([
            'url' => self::URL,
            'method' => 'POST',
            'headers' => ['X-Token' => '42'],
            'secret' => null,
            'mode' => WebhookAction::MODE_PER_BATCH,
            'includeAttributes' => false,
        ], $action->validateConfig(['url' => ' '.self::URL.' ', 'headers' => ['X-Token' => 42], 'secretSet' => true]));

        foreach ([
            'method' => ['method' => 'GET'],
            'mode' => ['mode' => 'each'],
            'includeAttributes' => ['includeAttributes' => 'yes'],
            'secret' => ['secret' => ['a']],
            'headers' => ['headers' => ['X-Phrasea-Signature' => 'forged']],
        ] as $path => $config) {
            try {
                $action->validateConfig(['url' => self::URL] + $config);
                $this->fail(sprintf('Expected a validation error for "%s"', $path));
            } catch (ValidationException $e) {
                $this->assertSame($path, $e->getConstraintViolationList()->get(0)->getPropertyPath(), $path);
            }
        }

        foreach ([['Bad Name' => 'x'], ['X-Injected' => "a\r\nHost: evil"]] as $headers) {
            try {
                $action->validateConfig(['url' => self::URL, 'headers' => $headers]);
                $this->fail('Expected a validation error');
            } catch (ValidationException $e) {
                $this->assertSame('headers', $e->getConstraintViolationList()->get(0)->getPropertyPath());
            }
        }
    }

    public function testSecretIsEncryptedAndMasked(): void
    {
        $encryptionManager = new EncryptionManager();
        $keyPair = $encryptionManager->generateKeyPair();
        $handler = new ActionConfigSecretsHandler(
            self::getService(AutomationActionRegistry::class),
            new SecretsManager($encryptionManager, $keyPair->getSecret(), $keyPair->getPublic()),
        );
        $config = $this->createAction()->validateConfig(['url' => self::URL, 'secret' => 's3cr3t']);

        $stored = $handler->encrypt(WebhookAction::TYPE, $config);
        $this->assertNotSame('s3cr3t', $stored['secret']);
        $this->assertSame('s3cr3t', $handler->decrypt(WebhookAction::TYPE, $stored)['secret']);

        $masked = $handler->mask(WebhookAction::TYPE, $stored);
        $this->assertNull($masked['secret']);
        $this->assertTrue($masked['secretSet']);

        // Sent back as is: the stored secret is kept
        $kept = $handler->encrypt(WebhookAction::TYPE, $this->createAction()->validateConfig($masked), $stored);
        $this->assertSame($stored['secret'], $kept['secret']);
    }
}
