<?php

declare(strict_types=1);

namespace App\Consumer\Middleware;

use App\Consumer\BulkOrigin;
use App\Consumer\WorkerQueue;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Marks the handling of a message received from a bulk transport, so that the
 * workflows it starts or continues keep their jobs on the bulk transports.
 */
final readonly class BulkOriginMiddleware implements MiddlewareInterface
{
    private const array BULK_TRANSPORTS = [
        WorkerQueue::TASK,
        WorkerQueue::BULK,
        WorkerQueue::BULK_HEAVY,
        WorkerQueue::BULK_HEAVY_LOW,
    ];

    public function __construct(
        private BulkOrigin $bulkOrigin,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $receivedStamp = $envelope->last(ReceivedStamp::class);
        if (!$receivedStamp instanceof ReceivedStamp || !in_array($receivedStamp->getTransportName(), self::BULK_TRANSPORTS, true)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->bulkOrigin->enter();
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->bulkOrigin->leave();
        }
    }
}
