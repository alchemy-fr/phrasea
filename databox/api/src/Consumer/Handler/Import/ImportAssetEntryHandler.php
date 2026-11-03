<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Import;

use App\Api\Mapper\Input\AssetInputMapper;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetImport;
use App\Model\AssetImportStatusEnum;
use App\Service\Import\ApiInputPersister;
use App\Service\Import\AssetImportInputFactory;
use App\Service\Import\AssetImportProgress;
use App\Service\Import\ImportErrorRecorder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Creates the asset of an entry as POST /assets would, as the import initiator.
 *
 * An entry never fails the message: its error is recorded instead, so that the import
 * always completes.
 */
#[AsMessageHandler]
final readonly class ImportAssetEntryHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserContextSwitcher $userContextSwitcher,
        private AssetImportInputFactory $inputFactory,
        private ApiInputPersister $apiInputPersister,
        private AssetImportProgress $progress,
        private ImportErrorRecorder $errorRecorder,
    ) {
    }

    public function __invoke(ImportAssetEntry $message): void
    {
        $import = $this->em->find(AssetImport::class, $message->importId);
        if (!$import instanceof AssetImport || !$this->isRunning($message->importId)) {
            // Deleted or failed: drop the entry silently
            return;
        }

        $this->importEntry($import, $message);

        $this->progress->finalizeIfComplete($message->importId);
    }

    private function importEntry(AssetImport $import, ImportAssetEntry $message): void
    {
        $importId = $import->getId();
        $workspaceId = $import->getWorkspaceId();
        $ownerId = $import->getOwnerId();
        $entry = $message->entry;
        $key = $entry['key'] ?? null;

        try {
            if (null !== $key && $this->keyExists($workspaceId, $key)) {
                $this->progress->increment($importId, AssetImportProgress::SKIPPED);

                return;
            }

            $this->userContextSwitcher->runAs($ownerId, function () use ($import, $entry, $message, $ownerId): void {
                $input = $this->inputFactory->create($import, $entry, $message->collectionId, $ownerId);
                $this->apiInputPersister->create(Asset::class, $input, [
                    AssetInputMapper::CONTEXT_CREATE_ONLY => true,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->errorRecorder->recoverEntityManager();
            if (null !== $key && $this->keyExists($workspaceId, $key)) {
                // Created by a concurrent entry since the check
                $this->progress->increment($importId, AssetImportProgress::SKIPPED);

                return;
            }

            $this->errorRecorder->recordFailure($importId, $message->line, $key, $e);

            return;
        } catch (\Throwable $e) {
            $this->errorRecorder->recordFailure($importId, $message->line, $key, $e);

            return;
        }

        $this->progress->increment($importId, AssetImportProgress::CREATED);
    }

    /**
     * Read from the database: the import entity may be outdated in the identity map.
     */
    private function isRunning(string $importId): bool
    {
        $status = $this->em->createQueryBuilder()
            ->select('t.status')
            ->from(AssetImport::class, 't')
            ->andWhere('t.id = :id')
            ->setParameter('id', $importId)
            ->getQuery()
            ->getSingleScalarResult();

        return in_array(AssetImportStatusEnum::tryFrom((int) $status), [
            AssetImportStatusEnum::Dispatching,
            AssetImportStatusEnum::Processing,
        ], true);
    }

    private function keyExists(string $workspaceId, string $key): bool
    {
        return null !== $this->em->createQueryBuilder()
            ->select('a.id')
            ->from(Asset::class, 'a')
            ->andWhere('a.workspace = :ws')
            ->andWhere('a.key = :key')
            ->setParameter('ws', $workspaceId)
            ->setParameter('key', $key)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
