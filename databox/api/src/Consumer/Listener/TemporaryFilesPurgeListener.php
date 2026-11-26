<?php

declare(strict_types=1);

namespace App\Consumer\Listener;

use App\Service\Storage\TemporaryFiles;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

/**
 * Deletes the temporary files left by a worker killed in the middle of a message
 * (OOM, timeout, deployment), which nothing else would ever remove.
 */
#[AsEventListener(WorkerStartedEvent::class)]
final readonly class TemporaryFilesPurgeListener
{
    /**
     * In seconds: far longer than the handling of any message.
     */
    public const int MAX_AGE = 86400;

    public function __construct(
        private TemporaryFiles $temporaryFiles,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerStartedEvent $event): void
    {
        try {
            $deleted = $this->temporaryFiles->purge(self::MAX_AGE);
        } catch (\Throwable $e) {
            // The purge must never prevent a worker from starting
            $this->logger->warning(sprintf('Unable to purge the stale temporary files: %s', $e->getMessage()), [
                'exception' => $e,
            ]);

            return;
        }

        if ($deleted > 0) {
            $this->logger->info(sprintf('%d stale temporary file(s) purged', $deleted));
        }
    }
}
