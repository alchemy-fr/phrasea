<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\AuthBundle\Client\ServiceAccountClient;
use Alchemy\AuthBundle\Security\DataboxIntegrationClient;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Security\DataboxIntegrationClientIds;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DataboxIntegrationClientIdsTest extends TestCase
{
    /**
     * @var list<string> levels of the logged messages
     */
    private array $logs = [];

    public function testAllowsTheClientsCreatedByDatabox(): void
    {
        $provider = $this->createProvider(new KeycloakClientTestMock());

        $this->assertTrue($provider->isClientAllowed('an-integration'));
        $this->assertSame([
            'clientId' => 'an-integration',
            'search' => 'false',
            'max' => 1,
        ], KeycloakClientTestMock::$lastQueries['/admin/realms/phrasea/clients']);
    }

    public function testRefusesTheOtherClients(): void
    {
        $provider = $this->createProvider(new KeycloakClientTestMock());

        $this->assertFalse($provider->isClientAllowed('databox-app'));
        $this->assertFalse($provider->isClientAllowed('unknown-client'));
        $this->assertSame([], $this->logs);
    }

    public function testAllowsTheClientsWhoseRedirectUrisAllLeadToTheDataboxIntegrations(): void
    {
        $client = [
            'clientId' => 'my-integration',
            'enabled' => true,
            'publicClient' => true,
            'standardFlowEnabled' => true,
            'rootUrl' => 'http://localhost',
            'redirectUris' => ['/integrations/*'],
        ];
        $httpClient = $this->createHttpClient(fn (): JsonMockResponse => new JsonMockResponse([$client]));

        $this->assertTrue($this->createProvider($httpClient)->isClientAllowed('my-integration'));
        // Not without the URL of the Databox API
        $this->assertFalse($this->createProvider($httpClient, '')->isClientAllowed('my-integration'));
    }

    public function testCachesTheAnswer(): void
    {
        $calls = 0;
        $provider = $this->createProvider($this->createHttpClient(function () use (&$calls): JsonMockResponse {
            ++$calls;

            return new JsonMockResponse([[
                'clientId' => 'an-integration',
                'enabled' => true,
                'attributes' => [DataboxIntegrationClient::CREATED_BY_ATTRIBUTE => DataboxIntegrationClient::CREATED_BY],
            ]]);
        }));

        $this->assertTrue($provider->isClientAllowed('an-integration'));
        $this->assertTrue($provider->isClientAllowed('an-integration'));
        $this->assertSame(1, $calls);
    }

    public function testRefusesADisabledClient(): void
    {
        $provider = $this->createProvider($this->createHttpClient(fn (): JsonMockResponse => new JsonMockResponse([[
            'clientId' => 'an-integration',
            'enabled' => false,
            'attributes' => [DataboxIntegrationClient::CREATED_BY_ATTRIBUTE => DataboxIntegrationClient::CREATED_BY],
        ]])));

        $this->assertFalse($provider->isClientAllowed('an-integration'));
    }

    public function testAKeycloakErrorRefusesTheClientForAShortTime(): void
    {
        $calls = 0;
        $provider = $this->createProvider($this->createHttpClient(function () use (&$calls): JsonMockResponse {
            ++$calls;

            return new JsonMockResponse(['error' => 'unavailable'], ['http_code' => 503]);
        }));

        $this->assertFalse($provider->isClientAllowed('an-integration'));
        $this->assertFalse($provider->isClientAllowed('an-integration'));
        $this->assertSame(1, $calls);
        $this->assertCount(1, $this->logs);
        $this->assertSame('error', $this->logs[0]);
    }

    /**
     * @param callable(): JsonMockResponse $onClients the answer to the clients query
     */
    private function createHttpClient(callable $onClients): HttpClientInterface
    {
        return new MockHttpClient(function (string $method, string $url) use ($onClients): JsonMockResponse {
            if (str_ends_with($url, '/token')) {
                return new JsonMockResponse([
                    'access_token' => 'service-account-token',
                    'expires_in' => 300,
                ]);
            }

            return $onClients();
        });
    }

    private function createProvider(HttpClientInterface $httpClient, string $databoxApiUrl = 'http://localhost'): DataboxIntegrationClientIds
    {
        $cache = new ArrayAdapter();
        $keycloakClient = new KeycloakClient(
            $httpClient,
            new KeycloakUrlGenerator('https://keycloak.test', null, 'phrasea'),
            $cache,
            'expose-admin',
            'secret',
        );

        $logger = new class(function (string $level): void {
            $this->logs[] = $level;
        }) extends AbstractLogger {
            public function __construct(private readonly \Closure $onLog)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                ($this->onLog)((string) $level);
            }
        };

        return new DataboxIntegrationClientIds(
            new ServiceAccountClient($keycloakClient, $cache),
            $keycloakClient,
            $cache,
            $logger,
            $databoxApiUrl,
        );
    }
}
