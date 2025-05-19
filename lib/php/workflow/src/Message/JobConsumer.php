<?php

namespace Alchemy\Workflow\Message;

use Alchemy\MessengerBundle\Message\RetryCountSupportInterface;

if (!interface_exists(RetryCountSupportInterface::class)) {
    require __DIR__ . '/RetryCountSupportInterface.php';
}

final readonly class JobConsumer implements RetryCountSupportInterface
{
    public function __construct(
        private string $workflowId,
        private string $jobId,
        private string $jobStateId,
    )
    {
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getJobStateId(): string
    {
        return $this->jobStateId;
    }
}
