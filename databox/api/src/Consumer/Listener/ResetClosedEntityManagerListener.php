<?php

declare(strict_types=1);

namespace App\Consumer\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * A database error (constraint violation, missing row, ...) closes the
 * EntityManager. Without a reset, every following message handled by the same
 * worker process fails with "The EntityManager is closed" until the process
 * hits its --limit and restarts.
 */
#[AsEventListener(WorkerMessageFailedEvent::class, method: 'onMessageFailed', priority: 255)]
final readonly class ResetClosedEntityManagerListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $managerRegistry,
        private LoggerInterface $logger,
    ) {
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($this->em->isOpen()) {
            return;
        }

        $this->logger->warning('EntityManager was closed after a message failure, resetting it', [
            'message' => $event->getEnvelope()->getMessage()::class,
            'error' => $event->getThrowable()->getMessage(),
        ]);

        $this->managerRegistry->resetManager();
    }
}
