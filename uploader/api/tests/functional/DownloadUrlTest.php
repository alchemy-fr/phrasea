<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Api\Processor\DownloadUrlProcessor;
use App\Consumer\Handler\Download;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DownloadUrlTest extends AbstractUploaderTestCase
{
    /**
     * @var array<string, array{env: mixed, server: mixed}>
     */
    private array $previousEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => ['env' => $env, 'server' => $server]) {
            if (null === $env) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
        $this->previousEnv = [];

        parent::tearDown();
    }

    /**
     * Read by the services built after it (the kernel is rebooted between requests).
     */
    private function setEnv(string $name, string $value): void
    {
        $this->previousEnv[$name] ??= ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null];
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    /**
     * The download is queued, not run.
     */
    private function download(string $userId): ResponseInterface
    {
        $client = static::createClient();
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $queue = $transport->intercept();

        $response = $client->request('POST', '/downloads', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
            'json' => [
                'url' => 'https://example.com/a.jpg',
                'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            ],
        ]);

        if (200 === $response->getStatusCode()) {
            $this->assertCount(1, array_filter(
                iterator_to_array($queue->getSent()),
                static fn ($envelope): bool => $envelope->getMessage() instanceof Download,
            ));
        }

        return $response;
    }

    public function testDownloadsAreRateLimitedPerUser(): void
    {
        $this->setEnv('DOWNLOAD_RATE_LIMIT', '2');
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getService('limiter.'.DownloadUrlProcessor::RATE_LIMITER);
        // Its state outlives the test
        $limiter->create(KeycloakClientTestMock::USER_UID)->reset();
        $limiter->create(KeycloakClientTestMock::OTHER_USER_UID)->reset();

        $this->assertSame(200, $this->download(KeycloakClientTestMock::USER_UID)->getStatusCode());
        $this->assertSame(200, $this->download(KeycloakClientTestMock::USER_UID)->getStatusCode());

        $response = $this->download(KeycloakClientTestMock::USER_UID);
        $this->assertSame(429, $response->getStatusCode());
        $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
        $this->assertNotNull($retryAfter, 'The client is told when to retry');
        $this->assertGreaterThanOrEqual(1, (int) $retryAfter);
        $this->assertLessThanOrEqual(3601, (int) $retryAfter);

        $this->assertSame(200, $this->download(KeycloakClientTestMock::OTHER_USER_UID)->getStatusCode(), 'Another user');
    }
}
