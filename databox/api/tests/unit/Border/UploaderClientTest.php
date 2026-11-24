<?php

declare(strict_types=1);

namespace App\Tests\Unit\Border;

use App\Border\UploaderClient;
use App\Border\UploaderOrigins;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class UploaderClientTest extends TestCase
{
    private const string UPLOADER_API_URL = 'http://10.0.0.5:8080';

    public function testRemoteUploaderCannotReachThePrivateNetwork(): void
    {
        $client = $this->createClient();

        $this->expectException(TransportException::class);
        $client->getCommit('http://169.254.169.254/latest', '42', 'token');
    }

    public function testUploaderOfTheInstanceMayLiveOnThePrivateNetwork(): void
    {
        $requests = [];
        $client = $this->createClient($requests);

        $this->assertSame(['id' => '42'], $client->getCommit(self::UPLOADER_API_URL, '42', 'token'));
        $this->assertSame(['GET '.self::UPLOADER_API_URL.'/commits/42'], $requests);
    }

    public function testRequestsAreBounded(): void
    {
        $options = [];
        $client = new UploaderClient(
            new MockHttpClient(function (string $method, string $url, array $opts) use (&$options): JsonMockResponse {
                $options = $opts;

                return new JsonMockResponse([]);
            }),
            new UploaderOrigins(self::UPLOADER_API_URL, null),
        );

        $client->getAsset(self::UPLOADER_API_URL, '42', 'token');
        $this->assertSame(60.0, (float) $options['max_duration']);
    }

    private function createClient(array &$requests = []): UploaderClient
    {
        return new UploaderClient(
            new MockHttpClient(function (string $method, string $url) use (&$requests): JsonMockResponse {
                $requests[] = $method.' '.$url;

                return new JsonMockResponse(['id' => '42']);
            }),
            new UploaderOrigins(self::UPLOADER_API_URL, null),
        );
    }
}
