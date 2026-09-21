<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Message;

use Pusher\Pusher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobUpdatePusherHandler
{
    public function __construct(
        private Pusher $pusher,
        private string $channelPrefix = 'workflow-',
    ) {
    }

    /**
     * Pusher errors (timeout, DNS) are left to Messenger's retry strategy.
     */
    public function __invoke(JobUpdatePusherMessage $message): void
    {
        $this->pusher->trigger($this->channelPrefix.$message->getWorkflowId(), 'job_update', [
            'jobId' => $message->getJobId(),
            'status' => $message->getStatus(),
        ]);
    }
}
