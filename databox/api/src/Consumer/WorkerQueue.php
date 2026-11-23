<?php

declare(strict_types=1);

namespace App\Consumer;

/**
 * The Messenger transports of databox, each one consumed by its own worker processes
 * (see doc/tech/Databox/workers.md).
 */
final class WorkerQueue
{
    /**
     * Real time: indexing, live updates, ACL.
     */
    public const string P1 = 'p1';

    /**
     * Standard: light workflow jobs, webhooks, notifications.
     */
    public const string P2 = 'p2';

    /**
     * Computation, file transfers and slow external APIs.
     */
    public const string HEAVY = 'heavy';

    /**
     * Every job of the workflows started by a bulk processing.
     */
    public const string BULK = 'bulk';

    /**
     * Bulk orchestrations.
     */
    public const string TASK = 'task';
}
