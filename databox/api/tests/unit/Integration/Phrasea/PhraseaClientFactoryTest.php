<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Phrasea;

use App\Entity\Integration\IntegrationToken;
use App\Integration\Auth\IntegrationTokenManager;
use App\Integration\IntegrationPrivateOrigins;
use App\Integration\Phrasea\PhraseaClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class PhraseaClientFactoryTest extends TestCase
{
    private const string EXPOSE_API_URL = 'http://10.0.0.6:8080';

    public function testARemoteExposeCannotReachThePrivateNetwork(): void
    {
        $requests = 0;
        $mock = new MockHttpClient(function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse([]);
        });
        $client = $this->createFactory($mock)->create('http://169.254.169.254', 'client', $this->createToken());

        try {
            $client->request('GET', '/publications');
            $this->fail('The request to the private network must be refused');
        } catch (TransportException) {
        }
        $this->assertSame(0, $requests);
    }

    public function testTheTokenRenewalOfARemoteExposeCannotReachThePrivateNetwork(): void
    {
        $requests = 0;
        $mock = new MockHttpClient(function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse([]);
        });
        $renew = $this->createFactory($mock)->createTokenRenewer('http://10.0.0.1', 'client');

        try {
            $renew('refresh');
            $this->fail('The request to the private network must be refused');
        } catch (TransportException) {
        }
        $this->assertSame(0, $requests);
    }

    public function testTheExposeOfTheInstanceMayLiveOnThePrivateNetwork(): void
    {
        $requests = [];
        $mock = new MockHttpClient(function (string $method, string $url) use (&$requests): JsonMockResponse {
            $requests[] = $method.' '.$url;

            return new JsonMockResponse(['access_token' => 'new']);
        });
        $factory = $this->createFactory($mock);

        $factory->create(self::EXPOSE_API_URL, 'client', $this->createToken())->request('GET', '/publications');
        $factory->createTokenRenewer(self::EXPOSE_API_URL, 'client')('refresh');

        $this->assertSame([
            'GET '.self::EXPOSE_API_URL.'/publications',
            'POST '.self::EXPOSE_API_URL.'/oauth/v2/token',
        ], $requests);
    }

    private function createFactory(MockHttpClient $mock): PhraseaClientFactory
    {
        return new PhraseaClientFactory(
            $mock,
            new IntegrationTokenManager($this->createStub(EntityManagerInterface::class)),
            new IntegrationPrivateOrigins(self::EXPOSE_API_URL, null, null),
        );
    }

    private function createToken(): IntegrationToken
    {
        $token = new IntegrationToken();
        $token->setToken(['access_token' => 'access']);
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        return $token;
    }
}
