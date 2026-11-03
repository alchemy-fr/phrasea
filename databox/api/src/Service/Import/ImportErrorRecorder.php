<?php

declare(strict_types=1);

namespace App\Service\Import;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Entity\Core\AssetImport;
use App\Entity\Core\AssetImportError;
use App\Service\Import\Exception\InvalidImportEntryException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Records the entries that cannot be imported, and counts them as failed.
 */
final readonly class ImportErrorRecorder
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $doctrine,
        private PostFlushStack $postFlushStack,
        private AssetImportProgress $progress,
        private LoggerInterface $logger,
    ) {
    }

    public function record(string $importId, int $line, ?string $key, string $message): void
    {
        $error = new AssetImportError($this->em->getReference(AssetImport::class, $importId), $line, $key, $message);
        $this->em->persist($error);
        $this->em->flush();
        $this->em->detach($error);

        $this->progress->increment($importId, AssetImportProgress::FAILED);
    }

    /**
     * Records the exception that stopped the import of an entry.
     */
    public function recordFailure(string $importId, int $line, ?string $key, \Throwable $exception): void
    {
        $this->recoverEntityManager();

        $this->record($importId, $line, $key, $this->describe($importId, $line, $exception));
    }

    /**
     * Rids the entity manager of what a failed attempt left in it.
     */
    public function recoverEntityManager(): void
    {
        // Messages of the failed attempt, e.g. the import of a file never persisted
        $this->postFlushStack->reset();

        if ($this->em->isOpen()) {
            $this->em->clear();
        } else {
            $this->doctrine->resetManager();
        }
    }

    private function describe(string $importId, int $line, \Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException,
            $exception instanceof InvalidImportEntryException,
            $exception instanceof HttpExceptionInterface,
            $exception instanceof AccessDeniedException,
            $exception instanceof AutomationOwnerUnavailableException,
            // e.g. a rendition definition not found by RenditionManager
            $exception instanceof \InvalidArgumentException => $exception->getMessage(),
            default => $this->describeUnexpected($importId, $line, $exception),
        };
    }

    private function describeUnexpected(string $importId, int $line, \Throwable $exception): string
    {
        $this->logger->error(sprintf('Asset import "%s", line %d: %s', $importId, $line, $exception->getMessage()), [
            'exception' => $exception,
        ]);

        return 'Unexpected error';
    }
}
