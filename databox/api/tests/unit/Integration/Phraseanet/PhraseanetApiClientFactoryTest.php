<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Phraseanet;

use App\Integration\IntegrationPrivateOrigins;
use App\Integration\Phraseanet\PhraseanetApiClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class PhraseanetApiClientFactoryTest extends TestCase
{
    private const string PHRASEANET_URL = 'http://10.0.0.7:8080';

    public function testARemotePhraseanetCannotReachThePrivateNetwork(): void
    {
        $requests = 0;
        $mock = new MockHttpClient(function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse([]);
        });
        $client = $this->createFactory($mock)->create('http://169.254.169.254/latest/', 'token');

        try {
            $client->request('GET', '/api/v3/me/');
            $this->fail('The request to the private network must be refused');
        } catch (TransportException) {
        }
        $this->assertSame(0, $requests);
    }

    public function testThePhraseanetOfTheInstanceMayLiveOnThePrivateNetwork(): void
    {
        $mock = new MockHttpClient(new JsonMockResponse(['ok' => true]));
        $client = $this->createFactory($mock)->create(self::PHRASEANET_URL, 'token');

        $this->assertSame(['ok' => true], $client->request('GET', '/api/v3/me/')->toArray());
    }

    public function testAListedOriginMayLiveOnThePrivateNetwork(): void
    {
        $mock = new MockHttpClient(new JsonMockResponse(['ok' => true]));
        $factory = new PhraseanetApiClientFactory($mock, new IntegrationPrivateOrigins(null, self::PHRASEANET_URL, 'https://10.0.0.8, http://10.0.0.9:8080'));

        $this->assertSame(['ok' => true], $factory->create('https://10.0.0.8/', 'token')->request('GET', '/api/v3/me/')->toArray());

        $this->expectException(TransportException::class);
        $factory->create('http://10.0.0.9/', 'token')->request('GET', '/api/v3/me/');
    }

    public function testUserInfoDoesNotOpenThePrivateNetwork(): void
    {
        $mock = new MockHttpClient(new JsonMockResponse([]));
        $client = $this->createFactory($mock)->create('http://10.0.0.7:8080@10.0.0.1/', 'token');

        $this->expectException(TransportException::class);
        $client->request('GET', '/api/v3/me/');
    }

    private function createFactory(MockHttpClient $mock): PhraseanetApiClientFactory
    {
        return new PhraseanetApiClientFactory($mock, new IntegrationPrivateOrigins(null, self::PHRASEANET_URL, null));
    }
}
