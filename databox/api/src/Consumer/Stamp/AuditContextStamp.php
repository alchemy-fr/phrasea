<?php

declare(strict_types=1);

namespace App\Consumer\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The audit context (actor, origin, correlation ID) of the action that dispatched the message.
 *
 * @see \App\Audit\AuditContext::resolve()
 */
final readonly class AuditContextStamp implements StampInterface
{
    public function __construct(
        public array $context = [],
    ) {
    }
}
