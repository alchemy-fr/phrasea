<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;
use App\Consumer\MessageDeduplication;
use App\Consumer\WorkerQueue;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[MessengerMessage('task')]
final readonly class IndexAllCollections
{
    public function __construct(public ?string $workspaceId = null)
    {
    }

    /**
     * The message to dispatch, coalesced: the collections of one workspace go to the heavy queue, so that
     * an access revoked on the workspace never waits behind a populate on the task one (the
     * permissions are filtered in Elasticsearch); those of every workspace stay on task.
     */
    public static function dispatchable(?string $workspaceId = null): Envelope
    {
        $envelope = MessageDeduplication::coalesce(new self($workspaceId));

        return null === $workspaceId ? $envelope : $envelope->with(new TransportNamesStamp([WorkerQueue::HEAVY]));
    }
}
