<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Import;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\AssetImport;
use App\Service\Import\AssetImportProgress;
use App\Service\Import\CollectionPathResolver;
use App\Service\Import\Exception\ImportFileException;
use App\Service\Import\ImportEntryNormalizer;
use App\Service\Import\ImportEntryReader;
use App\Service\Import\ImportErrorRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reads the instructions file and fans the import out: one message per valid entry, sent
 * as soon as the entry is read (the entries are never held in memory).
 */
#[AsMessageHandler]
final readonly class DispatchAssetImportHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private FileStorageManager $storageManager,
        private ImportEntryReader $entryReader,
        private ImportEntryNormalizer $entryNormalizer,
        private CollectionPathResolver $collectionPathResolver,
        private AssetImportProgress $progress,
        private ImportErrorRecorder $errorRecorder,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchAssetImport $message): void
    {
        $import = $this->em->find(AssetImport::class, $message->importId);
        if (!$import instanceof AssetImport || !$this->progress->startDispatch($message->importId)) {
            // Deleted, or already dispatched (redelivered message)
            return;
        }

        $importId = $import->getId();
        $workspaceId = $import->getWorkspaceId();
        $baseCollectionId = $import->getCollection()?->getId();
        $initiatorId = $import->getOwnerId();

        $stream = null;
        $total = 0;
        try {
            $stream = $this->storageManager->getStream($import->getSourcePath());
            $pathCache = [];

            foreach ($this->entryReader->iterate($stream, $import->getFormat()) as $line => $entry) {
                ++$total;
                if (is_string($entry)) {
                    $this->errorRecorder->record($importId, $line, null, $entry);

                    continue;
                }

                $key = is_scalar($entry['key'] ?? null) ? (string) $entry['key'] : null;
                try {
                    $entry = $this->entryNormalizer->normalize($entry);

                    $collectionId = null;
                    if (isset($entry['path'])) {
                        $collectionId = $this->collectionPathResolver->resolve($workspaceId, $baseCollectionId, $entry['path'], $initiatorId, $pathCache);
                        unset($entry['path']);
                    }
                } catch (\Throwable $e) {
                    $this->errorRecorder->recordFailure($importId, $line, $key, $e);

                    continue;
                }

                $this->bus->dispatch(new ImportAssetEntry($importId, $line, $entry, $collectionId));
            }
        } catch (ImportFileException $e) {
            $this->progress->fail($importId, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            // A redelivered message would be ignored: the import must not stay "dispatching"
            $this->logger->error(sprintf('Asset import "%s" failed: %s', $importId, $e->getMessage()), [
                'exception' => $e,
            ]);
            $this->progress->fail($importId, 'Unexpected error while reading the file');

            return;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->progress->endDispatch($importId, $total);
        $this->progress->finalizeIfComplete($importId);
    }
}
