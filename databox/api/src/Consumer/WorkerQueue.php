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
     * Heavy jobs that are not urgent: the renditions not marked "build first", the AI integrations.
     */
    public const string HEAVY_LOW = 'heavy_low';

    /**
     * The light jobs of the workflows started by a bulk processing.
     */
    public const string BULK = 'bulk';

    /**
     * The heavy jobs of the workflows started by a bulk processing.
     */
    public const string BULK_HEAVY = 'bulk_heavy';

    /**
     * The non urgent heavy jobs of the workflows started by a bulk processing.
     */
    public const string BULK_HEAVY_LOW = 'bulk_heavy_low';

    /**
     * Bulk orchestrations.
     */
    public const string TASK = 'task';
}
