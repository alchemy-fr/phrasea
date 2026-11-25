<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Pusher;

use Alchemy\CoreBundle\Pusher\CircuitOpenException;
use Alchemy\CoreBundle\Pusher\PusherFactory;
use Alchemy\CoreBundle\Pusher\PusherManager;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;
use Symfony\Component\Messenger\MessageBusInterface;

class PusherManagerTest extends TestCase
{
    public function testOnlyAPusherWithAHostIsConfigured(): void
    {
        $this->assertFalse(PusherManager::isConfigured(PusherFactory::create('', 'key', 'secret', 'app')));
        $this->assertTrue(PusherManager::isConfigured(PusherFactory::create('sockudo', 'key', 'secret', 'app')));
    }

    public function testTheFactorySetsAShortTimeout(): void
    {
        $this->assertSame(2, PusherFactory::create('sockudo', 'key', 'secret', 'app')->getSettings()['timeout']);
    }

    #[DataProvider('provideFailures')]
    public function testADirectPushFailureIsOnlyLogged(\Throwable $exception, string $expectedLevel): void
    {
        $pusher = $this->createMock(Pusher::class);
        $pusher->method('getSettings')->willReturn(['host' => 'sockudo']);
        $pusher->expects($this->once())
            ->method('trigger')
            ->with('asset.a1', 'asset_ingested', [])
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($expectedLevel, $this->stringContains('{event}'), $this->callback(
                fn (array $context): bool => 'asset_ingested' === $context['event']
                    && 'asset.a1' === $context['channel']
                    && $exception === $context['exception'],
            ));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        new PusherManager($pusher, $bus, logger: $logger)->trigger('asset/a1', 'asset_ingested', [], direct: true);
    }

    public static function provideFailures(): iterable
    {
        $request = new Request('POST', 'http://sockudo');

        yield 'timeout' => [new ApiErrorException('cURL error 28: Operation timed out', 0, new ConnectException('cURL error 28', $request)), LogLevel::WARNING];
        yield 'connection refused, unwrapped' => [new ConnectException('Connection refused', $request), LogLevel::WARNING];
        yield 'skipped by the open circuit' => [new ApiErrorException('Sockudo unreachable', 0, new CircuitOpenException('Sockudo unreachable', $request)), LogLevel::DEBUG];
        yield 'unknown application' => [new ApiErrorException('{"error":"app_not_found"}', 404), LogLevel::ERROR];
        yield 'invalid response' => [new PusherException('Data decoding error.'), LogLevel::ERROR];
    }

    public function testADisabledPusherDoesNotPush(): void
    {
        $pusher = $this->createMock(Pusher::class);
        $pusher->method('getSettings')->willReturn(['host' => 'sockudo']);
        $pusher->expects($this->never())->method('trigger');

        new PusherManager($pusher, $this->createMock(MessageBusInterface::class), true)->trigger('c', 'e', [], direct: true);
    }
}
