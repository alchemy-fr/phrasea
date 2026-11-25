<?php

namespace Alchemy\WorkflowBundle\Listener;

use Alchemy\Workflow\Listener\JobUpdateEvent;
use Alchemy\Workflow\Listener\WorkflowUpdateEvent;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Pushes synchronously, from within the workflow job: an unreachable Pusher
 * host is only logged so that it never fails the job itself.
 */
#[AsEventListener(JobUpdateEvent::class, method: 'jobUpdate')]
#[AsEventListener(WorkflowUpdateEvent::class, method: 'workflowUpdate')]
final readonly class PusherListener
{
    public function __construct(
        private Pusher $pusher,
        private LoggerInterface $logger,
        private string $channelPrefix = 'workflow-',
    ) {
    }

    public function jobUpdate(JobUpdateEvent $event): void
    {
        $this->trigger($event->getWorkflowId(), 'job_update', [
            'jobId' => $event->getJobId(),
            'status' => $event->getStatus(),
        ]);
    }

    public function workflowUpdate(WorkflowUpdateEvent $event): void
    {
        $state = $event->getState();

        $this->trigger($state->getId(), 'workflow_update', [
            'status' => $state->getStatus(),
        ]);
    }

    private function trigger(string $workflowId, string $event, array $payload): void
    {
        // A stack deployed without a real-time server has no Sockudo host
        if ('' === ($this->pusher->getSettings()['host'] ?? '')) {
            return;
        }

        try {
            $this->pusher->trigger($this->channelPrefix.$workflowId, $event, $payload);
        } catch (PusherException|GuzzleException $e) {
            // A transport failure (timeout, connection refused, skipped by an open
            // circuit breaker) is transient, unlike an error answered by Sockudo.
            $transport = $e instanceof NetworkExceptionInterface || ($e instanceof ApiErrorException && 0 === $e->getCode());

            $this->logger->log($transport ? LogLevel::WARNING : LogLevel::ERROR, 'Failed to push {event}: {error}', [
                'event' => $event,
                'error' => $e->getMessage(),
                'workflowId' => $workflowId,
                'payload' => $payload,
                'exception' => $e,
            ]);
        }
    }
}
