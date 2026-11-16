<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Cache;

use Alchemy\MessengerBundle\Listener\TerminateStackListener;
use App\Http\Cache\ProxyCachePurger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProxyCachePurgerTest extends TestCase
{
    private const string CLIENT_ORIGIN = 'https://expose.test';

    /**
     * @var list<array{url: string, origin: string|null, accept: string|null}>
     */
    private array $requests = [];

    public function testPurgesEveryCachedVariantOfThePath(): void
    {
        $terminate = new TerminateStackListener($this->createStub(MessageBusInterface::class));
        $purger = $this->createPurger($terminate, self::CLIENT_ORIGIN);

        $purger->purgeUri('/publications/abc');
        $purger->purgeUri('/publications/abc?jwt=foo');
        $purger->purgeRoute('get_publication', ['id' => 'abc']);
        $this->assertSame([], $this->requests, 'Purges wait for the end of the request');

        $terminate->onTerminate();

        $expected = [];
        foreach (ProxyCachePurger::ACCEPT_HEADERS as $accept) {
            foreach ([null, self::CLIENT_ORIGIN] as $origin) {
                $expected[] = [
                    'url' => 'http://expose-api-nginx/purge/publications/abc',
                    'origin' => $origin,
                    'accept' => $accept,
                ];
            }
        }
        $this->assertEqualsCanonicalizing($expected, $this->requests);
    }

    public function testWithoutClientOriginOnlyTheRequestsWithoutOriginArePurged(): void
    {
        $terminate = new TerminateStackListener($this->createStub(MessageBusInterface::class));
        $purger = $this->createPurger($terminate, '');

        $purger->purgeUri('/assets/abc');
        $terminate->onTerminate();

        $this->assertCount(count(ProxyCachePurger::ACCEPT_HEADERS), $this->requests);
        $this->assertSame([null], array_values(array_unique(array_column($this->requests, 'origin'))));
    }

    public function testServerErrorsAreNotIgnored(): void
    {
        $terminate = new TerminateStackListener($this->createStub(MessageBusInterface::class));
        $purger = $this->createPurger($terminate, self::CLIENT_ORIGIN, 500);

        $purger->purgeUri('/assets/abc');

        $this->expectException(ServerException::class);
        $terminate->onTerminate();
    }

    private function createPurger(TerminateStackListener $terminate, string $clientOrigin, int $status = 404): ProxyCachePurger
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($status): MockResponse {
            $this->assertSame('GET', $method);
            $headers = $options['normalized_headers'];
            $this->requests[] = [
                'url' => $url,
                'origin' => isset($headers['origin'][0]) ? substr($headers['origin'][0], strlen('Origin: ')) : null,
                'accept' => isset($headers['accept'][0]) ? substr($headers['accept'][0], strlen('Accept: ')) : null,
            ];

            return new MockResponse('', ['http_code' => $status]);
        }, 'http://expose-api-nginx');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $name, array $parameters): string => '/publications/'.$parameters['id'],
        );

        return new ProxyCachePurger($client, $urlGenerator, $terminate, $clientOrigin);
    }
}
