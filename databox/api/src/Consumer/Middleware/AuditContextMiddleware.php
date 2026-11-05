<?php

declare(strict_types=1);

namespace App\Consumer\Middleware;

use Alchemy\Workflow\Message\JobConsumer;
use App\Audit\AuditContext;
use App\Consumer\Stamp\AuditContextStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Carries the audit context of the dispatching action to the handler of the message,
 * so that the asynchronous effects of an action are logged with its actor and correlation ID.
 * The URI and route of the original request are kept, the source becomes the worker.
 */
final readonly class AuditContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuditContext $auditContext,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ConsumedByWorkerStamp::class)) {
            if (null === $envelope->last(AuditContextStamp::class)) {
                $envelope = $envelope->with(new AuditContextStamp($this->auditContext->resolve()));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        $stamp = $envelope->last(AuditContextStamp::class);
        $previous = $this->auditContext->save();
        $this->auditContext->restore([
            // Without stamp, the system acts
            ...($stamp instanceof AuditContextStamp ? $stamp->context : []),
            'source' => $envelope->getMessage() instanceof JobConsumer
                ? AuditContext::SOURCE_INTEGRATION
                : AuditContext::SOURCE_WORKER,
        ]);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->auditContext->load($previous);
        }
    }
}
