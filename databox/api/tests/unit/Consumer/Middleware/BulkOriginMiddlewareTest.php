<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer\Middleware;

use App\Consumer\BulkOrigin;
use App\Consumer\Middleware\BulkOriginMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class BulkOriginMiddlewareTest extends TestCase
{
    #[DataProvider('getEnvelopes')]
    public function testBulkOriginIsActiveWhileHandlingABulkMessage(Envelope $envelope, bool $expected): void
    {
        $bulkOrigin = new BulkOrigin();
        $activeDuringHandling = null;

        new BulkOriginMiddleware($bulkOrigin)->handle($envelope, $this->createStack(function () use ($bulkOrigin, &$activeDuringHandling): void {
            $activeDuringHandling = $bulkOrigin->isActive();
        }));

        $this->assertSame($expected, $activeDuringHandling);
        $this->assertFalse($bulkOrigin->isActive());
    }

    public static function getEnvelopes(): iterable
    {
        yield 'received from task' => [new Envelope(new \stdClass(), [new ReceivedStamp('task')]), true];
        yield 'received from bulk' => [new Envelope(new \stdClass(), [new ReceivedStamp('bulk')]), true];
        yield 'received from bulk_heavy' => [new Envelope(new \stdClass(), [new ReceivedStamp('bulk_heavy')]), true];
        yield 'received from bulk_heavy_low' => [new Envelope(new \stdClass(), [new ReceivedStamp('bulk_heavy_low')]), true];
        yield 'received from p2' => [new Envelope(new \stdClass(), [new ReceivedStamp('p2')]), false];
        yield 'received from heavy' => [new Envelope(new \stdClass(), [new ReceivedStamp('heavy')]), false];
        yield 'received from heavy_low' => [new Envelope(new \stdClass(), [new ReceivedStamp('heavy_low')]), false];
        yield 'dispatched' => [new Envelope(new \stdClass()), false];
    }

    public function testBulkOriginIsLeftWhenTheHandlerFails(): void
    {
        $bulkOrigin = new BulkOrigin();
        $middleware = new BulkOriginMiddleware($bulkOrigin);

        try {
            $middleware->handle(new Envelope(new \stdClass(), [new ReceivedStamp('task')]), $this->createStack(function (): void {
                throw new \RuntimeException('boom');
            }));
            $this->fail('The exception should be thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertFalse($bulkOrigin->isActive());
    }

    public function testNestedBulkMessagesKeepTheOriginUntilTheOuterOneEnds(): void
    {
        $bulkOrigin = new BulkOrigin();
        $middleware = new BulkOriginMiddleware($bulkOrigin);
        $activeAfterInner = null;

        $middleware->handle(new Envelope(new \stdClass(), [new ReceivedStamp('task')]), $this->createStack(function () use ($middleware, $bulkOrigin, &$activeAfterInner): void {
            $middleware->handle(new Envelope(new \stdClass(), [new ReceivedStamp('bulk')]), $this->createStack());
            $activeAfterInner = $bulkOrigin->isActive();
        }));

        $this->assertTrue($activeAfterInner);
        $this->assertFalse($bulkOrigin->isActive());
    }

    private function createStack(?\Closure $handler = null): StackInterface
    {
        return new StackMiddleware(new class($handler) implements MiddlewareInterface {
            public function __construct(private readonly ?\Closure $handler)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                if (null !== $this->handler) {
                    ($this->handler)();
                }

                return $envelope;
            }
        });
    }
}
