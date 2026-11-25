<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Pusher;

use Alchemy\CoreBundle\Pusher\CircuitBreakerMiddleware;
use Alchemy\CoreBundle\Pusher\CircuitOpenException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CircuitBreakerMiddlewareTest extends TestCase
{
    private float $clock = 1000.0;

    private function createClient(MockHandler $mock): Client
    {
        $stack = HandlerStack::create($mock);
        $stack->push(new CircuitBreakerMiddleware(30, fn (): float => $this->clock), 'circuit_breaker');

        return new Client(['handler' => $stack, 'http_errors' => false]);
    }

    private static function connectException(): ConnectException
    {
        return new ConnectException('Connection refused', new Request('POST', 'http://sockudo'));
    }

    public function testANetworkFailureOpensTheCircuit(): void
    {
        $mock = new MockHandler([self::connectException(), new Response(200)]);
        $client = $this->createClient($mock);

        try {
            $client->post('http://sockudo/apps/1/events');
            $this->fail('The network failure must be rethrown');
        } catch (ConnectException $e) {
            $this->assertNotInstanceOf(CircuitOpenException::class, $e);
        }

        $this->clock += 29;
        try {
            $client->post('http://sockudo/apps/1/events');
            $this->fail('The open circuit must reject the request');
        } catch (CircuitOpenException $e) {
            $this->assertStringContainsString('suspended for 1 s', $e->getMessage());
        }

        $this->assertCount(1, $mock, 'The rejected request must not reach the handler');
    }

    public function testTheRequestsGoThroughAgainAfterTheCooldown(): void
    {
        $mock = new MockHandler([self::connectException(), new Response(200), new Response(200)]);
        $client = $this->createClient($mock);

        try {
            $client->post('http://sockudo/apps/1/events');
        } catch (ConnectException) {
        }

        $this->clock += 30;
        $this->assertSame(200, $client->post('http://sockudo/apps/1/events')->getStatusCode());
        $this->assertSame(200, $client->post('http://sockudo/apps/1/events')->getStatusCode());
        $this->assertCount(0, $mock);
    }

    public function testASuccessClosesTheCircuit(): void
    {
        $mock = new MockHandler([
            self::connectException(),
            new Response(200),
            self::connectException(),
            new Response(200),
        ]);
        $client = $this->createClient($mock);

        try {
            $client->post('http://sockudo/apps/1/events');
        } catch (ConnectException) {
        }

        $this->clock += 31;
        $client->post('http://sockudo/apps/1/events');

        // A new failure opens the circuit again for a full cooldown
        try {
            $client->post('http://sockudo/apps/1/events');
            $this->fail('The network failure must be rethrown');
        } catch (ConnectException $e) {
            $this->assertNotInstanceOf(CircuitOpenException::class, $e);
        }

        $this->expectException(CircuitOpenException::class);
        $client->post('http://sockudo/apps/1/events');
    }

    #[DataProvider('provideErrorStatuses')]
    public function testAnHttpErrorDoesNotOpenTheCircuit(int $status): void
    {
        $mock = new MockHandler([new Response($status), new Response(200)]);
        $client = $this->createClient($mock);

        $this->assertSame($status, $client->post('http://sockudo/apps/1/events')->getStatusCode());
        $this->assertSame(200, $client->post('http://sockudo/apps/1/events')->getStatusCode());
    }

    public static function provideErrorStatuses(): iterable
    {
        yield 'not found' => [404];
        yield 'server error' => [500];
    }
}
