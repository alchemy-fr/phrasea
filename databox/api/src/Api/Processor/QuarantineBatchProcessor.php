<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\QuarantineBatchInput;
use App\Api\Model\Output\QuarantineBatchOutput;
use App\Consumer\Handler\Asset\AssetsDelete;
use App\Entity\Core\Asset;
use App\Model\QuarantineBatchActionEnum;
use App\Service\Asset\QuarantineResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Runs one quarantine action on a batch of assets. The eligibility of each
 * asset is checked again (its state may have changed since the selection):
 * a no longer eligible asset is skipped, and an asset failing does not stop
 * the others.
 */
final readonly class QuarantineBatchProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $registry,
        private QuarantineResolver $quarantineResolver,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private PostFlushStack $postFlushStack,
    ) {
    }

    /**
     * @param QuarantineBatchInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuarantineBatchOutput
    {
        $action = $data->action;
        $addAsVersion = QuarantineBatchActionEnum::Merge === $action && $data->addAsVersion;

        /** @var array<string, array{id: string, status: string, reason?: string}> $results */
        $results = [];
        // Removed at once, through the same message as POST /assets/delete-multiple
        $toDelete = [];

        foreach ($data->items as $item) {
            $id = (string) $item['id'];
            if (isset($results[$id])) {
                continue;
            }

            $mark = $this->postFlushStack->mark();
            try {
                $asset = Uuid::isValid($id) ? $this->em->find(Asset::class, $id) : null;
                $mergeTargets = QuarantineBatchActionEnum::Merge === $action && null !== $asset
                    && null === $this->quarantineResolver->checkQuarantined($asset)
                    ? $this->quarantineResolver->resolveMergeTargets($asset)
                    : null;

                $reason = $this->quarantineResolver->checkEligibility($asset, $action, $data->addAsVersion, $mergeTargets);
                $target = null;
                if (null === $reason && $addAsVersion) {
                    $target = $this->quarantineResolver->resolveMergeTarget($mergeTargets ?? [], $item['targetId'] ?? null);
                    if (is_string($target)) {
                        $reason = $target;
                    }
                }

                if (null !== $reason || null === $asset) {
                    $results[$id] = ['id' => $id, 'status' => QuarantineBatchOutput::STATUS_SKIPPED, 'reason' => $reason ?? QuarantineResolver::REASON_NOT_FOUND];

                    continue;
                }

                if (QuarantineBatchActionEnum::Bypass === $action) {
                    $this->quarantineResolver->bypass($asset);
                } elseif ($target instanceof Asset) {
                    $this->quarantineResolver->addAsVersion($asset, $target);
                } else {
                    $toDelete[] = $id;

                    continue;
                }

                $results[$id] = ['id' => $id, 'status' => QuarantineBatchOutput::STATUS_PROCESSED];
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Quarantine batch "%s" failed on asset %s: %s', $action->value, $id, $e->getMessage()), [
                    'exception' => $e,
                ]);
                $results[$id] = ['id' => $id, 'status' => QuarantineBatchOutput::STATUS_FAILED, 'reason' => QuarantineBatchOutput::REASON_ERROR];
                $this->recoverEntityManager($mark);
            }
        }

        if (!empty($toDelete)) {
            $status = QuarantineBatchOutput::STATUS_PROCESSED;
            try {
                $this->bus->dispatch(new AssetsDelete($toDelete, [], QuarantineBatchActionEnum::Trash !== $action));
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Quarantine batch "%s" failed: %s', $action->value, $e->getMessage()), [
                    'exception' => $e,
                ]);
                $status = QuarantineBatchOutput::STATUS_FAILED;
            }
            foreach ($toDelete as $id) {
                $results[$id] = ['id' => $id, 'status' => $status] + (QuarantineBatchOutput::STATUS_FAILED === $status ? ['reason' => QuarantineBatchOutput::REASON_ERROR] : []);
            }
        }

        $ordered = [];
        foreach ($data->items as $item) {
            $id = (string) $item['id'];
            if (isset($results[$id])) {
                $ordered[] = $results[$id];
                unset($results[$id]);
            }
        }

        return new QuarantineBatchOutput($ordered);
    }

    /**
     * A failed asset must not leave its pending changes to the next flush, its
     * post-flush callbacks (e.g. the workflow of a merge target) to the end of
     * the request, nor a closed entity manager to the next assets.
     *
     * @param array{int, int} $mark taken before the asset, keeps what the assets processed before it stacked
     */
    private function recoverEntityManager(array $mark): void
    {
        $this->postFlushStack->rollbackTo($mark);

        if ($this->em->isOpen()) {
            $this->em->clear();
        } else {
            $this->registry->resetManager();
        }
    }
}
