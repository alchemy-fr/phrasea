<?php

declare(strict_types=1);

namespace App\Automation;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Automation\Action\AutomationActionInterface;
use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Consumer\Handler\Automation\RunAutomation;
use App\Entity\Automation\AutomationRun;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Asset;
use App\Model\AutomationRunStatusEnum;
use App\Model\AutomationScopeEnum;
use App\Model\AutomationTriggerEnum;
use App\Notification\ExceptionNotifier;
use App\Notification\UserNotifyableException;
use App\Repository\Automation\AutomationSeenAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Runs the automations: one run at a time per automation, as the owner of the saved search.
 *
 * The cursor only moves forward on success: an asset is marked as seen once every action
 * succeeded on it, a failed asset stays "new" and is retried at the next run.
 */
final readonly class AutomationRunner
{
    // Refreshed after each batch
    private const int LOCK_TTL = 600;

    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $managerRegistry,
        private MessageBusInterface $bus,
        private LockFactory $lockFactory,
        private UserContextSwitcher $userContextSwitcher,
        private AutomationAssetIterator $assetIterator,
        private AutomationActionRegistry $actionRegistry,
        private ActionConfigSecretsHandler $secretsHandler,
        private AutomationSeenAssetRepository $seenAssetRepository,
        private ExceptionNotifier $exceptionNotifier,
        private LoggerInterface $logger,
    ) {
    }

    public static function getLockKey(string $automationId): string
    {
        return 'automation_run_'.$automationId;
    }

    /**
     * Creates a pending run and dispatches it to the worker.
     */
    public function trigger(
        SavedSearchAutomation $automation,
        AutomationTriggerEnum $trigger,
        ?string $initiatorId,
        bool $dryRun = false,
    ): AutomationRun {
        $run = new AutomationRun();
        $run->setAutomation($automation);
        $run->setTrigger($trigger);
        $run->setInitiatorId($initiatorId);
        $run->setDryRun($dryRun);

        $this->em->persist($run);
        $this->em->flush();

        $this->bus->dispatch(new RunAutomation($run->getId()));

        return $run;
    }

    /**
     * Runs a pending run. The entity manager is cleared during the run: reload the entities afterwards.
     */
    public function run(AutomationRun $run): void
    {
        if (AutomationRunStatusEnum::Pending !== $run->getStatus()) {
            return;
        }

        $runId = $run->getId();
        $automation = $run->getAutomation();
        $automationId = $automation->getId();

        $lock = $this->lockFactory->createLock(self::getLockKey($automationId), self::LOCK_TTL);
        if (!$lock->acquire()) {
            $now = new \DateTimeImmutable();
            $run->setStatus(AutomationRunStatusEnum::Skipped);
            $run->setStartedAt($now);
            $run->setEndedAt($now);
            $run->setError('Another run of this automation is in progress');
            $this->em->flush();

            return;
        }

        try {
            $run->setStatus(AutomationRunStatusEnum::Running);
            $run->setStartedAt(new \DateTimeImmutable());
            $this->em->flush();

            /** @var \ArrayObject<int, AutomationActionState> $states */
            $states = new \ArrayObject();
            $error = null;
            $ownerUnavailable = false;
            try {
                $ownerId = $automation->getOwnerId() ?? throw new AutomationOwnerUnavailableException('The saved search has no owner');
                $this->userContextSwitcher->runAs(
                    $ownerId,
                    fn (JwtUser $user) => $this->execute($runId, $automationId, $user, $lock, $states),
                );
            } catch (AutomationOwnerUnavailableException $e) {
                $error = $e;
                $ownerUnavailable = true;
            } catch (\Throwable $e) {
                $error = $e;
                $this->logger->error(sprintf('Automation "%s" run "%s" failed: %s', $automationId, $runId, $e->getMessage()), [
                    'exception' => $e,
                ]);
            }

            $this->finalize($runId, $automationId, $states, $error, $ownerUnavailable);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param \ArrayObject<int, AutomationActionState> $states
     */
    private function execute(string $runId, string $automationId, JwtUser $user, LockInterface $lock, \ArrayObject $states): void
    {
        $run = $this->em->find(AutomationRun::class, $runId);
        $automation = $this->em->find(SavedSearchAutomation::class, $automationId);
        $savedSearch = $automation->getSavedSearch();

        $context = new AutomationRunContext(
            runId: $runId,
            automationId: $automationId,
            automationName: (string) $automation->getName(),
            savedSearchId: $savedSearch->getId(),
            savedSearchName: $savedSearch->getName(),
            ownerId: $user->getId(),
            trigger: $run->getTrigger(),
            dryRun: $run->isDryRun(),
        );

        $actions = [];
        foreach ($automation->getActions() as $definition) {
            $type = (string) ($definition['type'] ?? '');
            $state = new AutomationActionState($type);
            $states[] = $state;
            $actions[] = [
                'action' => $this->actionRegistry->get($type),
                'config' => $this->secretsHandler->decrypt($type, $definition['config'] ?? []),
                'state' => $state,
            ];
        }

        $counts = ['matched' => 0, 'new' => 0, 'processed' => 0, 'failed' => 0];
        $onlyNew = AutomationScopeEnum::New === $automation->getScope();
        foreach ($this->assetIterator->iterate($automation, $user, $onlyNew) as $batch) {
            $counts['matched'] += $batch->matchedCount;
            $counts['new'] += $batch->newCount;

            if (!$context->dryRun && !empty($batch->assets)) {
                $succeeded = $this->processBatch($batch->assets, $actions, $context);
                $counts['processed'] += count($succeeded);
                $counts['failed'] += count($batch->assets) - count($succeeded);
                $this->seenAssetRepository->markSeen($automationId, $succeeded, $runId);
            }

            $this->saveProgress($runId, $counts, $states);
            $lock->refresh();
        }

        $this->saveProgress($runId, $counts, $states);

        if ($context->dryRun) {
            return;
        }

        foreach ($actions as $action) {
            try {
                $action['action']->finish($action['state'], $action['config'], $context);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Automation action "%s" failed to finish: %s', $action['state']->type, $e->getMessage()), [
                    'exception' => $e,
                ]);
                $action['state']->setError($e->getMessage());
                $this->resetClosedEntityManager();
            }
        }
    }

    /**
     * Applies the actions in order: an asset failed by an action is not handed to the next ones.
     *
     * @param Asset[]                                                                                     $assets
     * @param list<array{action: AutomationActionInterface, config: array, state: AutomationActionState}> $actions
     *
     * @return string[] the IDs of the assets every action succeeded on
     */
    private function processBatch(array $assets, array $actions, AutomationRunContext $context): array
    {
        $remaining = $assets;
        $aborted = false;
        foreach ($actions as $action) {
            $state = $action['state'];
            $state->beginBatch();

            if ($aborted) {
                foreach ($remaining as $asset) {
                    $state->markFailed($asset, 'Not run: a previous action failed');
                }
                $remaining = [];
                continue;
            }

            try {
                $action['action']->processBatch(new AutomationBatch($remaining, $action['config'], $state, $context));
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Automation action "%s" failed: %s', $state->type, $e->getMessage()), [
                    'exception' => $e,
                ]);
                foreach ($remaining as $asset) {
                    if (!$state->isHandledInBatch($asset)) {
                        $state->markFailed($asset, $e->getMessage());
                    }
                }
                // The assets are detached from a reset entity manager: the next actions cannot use them
                $aborted = $this->resetClosedEntityManager();
            }

            $remaining = array_values(array_filter($remaining, fn (Asset $asset): bool => !$state->isFailedInBatch($asset)));
        }

        return array_map(fn (Asset $asset): string => $asset->getId(), $remaining);
    }

    /**
     * Persists the progress and frees the memory before the next batch.
     *
     * @param array{matched: int, new: int, processed: int, failed: int} $counts
     * @param \ArrayObject<int, AutomationActionState>                   $states
     */
    private function saveProgress(string $runId, array $counts, \ArrayObject $states): void
    {
        $this->resetClosedEntityManager();

        $run = $this->em->find(AutomationRun::class, $runId)
            ?? throw new \RuntimeException('The automation was deleted during its run');
        $run->setMatchedCount($counts['matched']);
        $run->setNewCount($counts['new']);
        $run->setProcessedCount($counts['processed']);
        $run->setFailedCount($counts['failed']);
        $run->setActionResults(self::normalizeStates($states));
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param \ArrayObject<int, AutomationActionState> $states
     */
    private function finalize(string $runId, string $automationId, \ArrayObject $states, ?\Throwable $error, bool $ownerUnavailable): void
    {
        $this->resetClosedEntityManager();

        $run = $this->em->find(AutomationRun::class, $runId);
        $automation = $this->em->find(SavedSearchAutomation::class, $automationId);
        if (null === $run || null === $automation) {
            // Deleted during the run
            return;
        }
        $now = new \DateTimeImmutable();

        $run->setActionResults(self::normalizeStates($states));
        if (null !== $error) {
            $status = AutomationRunStatusEnum::Failed;
            $run->setError($error->getMessage());
        } else {
            $status = $run->getFailedCount() > 0 ? AutomationRunStatusEnum::Partial : AutomationRunStatusEnum::Success;
        }
        $run->setStatus($status);
        $run->setEndedAt($now);

        if (!$run->isDryRun()) {
            $automation->setLastRunAt($now);
            $automation->setLastRunStatus($status);

            if (AutomationRunStatusEnum::Failed === $status) {
                if (AutomationTriggerEnum::Schedule === $run->getTrigger()) {
                    $automation->setConsecutiveFailures($automation->getConsecutiveFailures() + 1);
                }
            } else {
                $automation->setConsecutiveFailures(0);
            }

            $disableReason = match (true) {
                $ownerUnavailable => sprintf('the owner of the saved search is not available (%s)', $error?->getMessage()),
                $automation->getConsecutiveFailures() >= SavedSearchAutomation::MAX_CONSECUTIVE_FAILURES => sprintf('its last %d scheduled runs failed (%s)', $automation->getConsecutiveFailures(), $error?->getMessage()),
                default => null,
            };

            if (null !== $disableReason && $automation->isEnabled()) {
                $automation->setEnabled(false);
                $automation->setNextRunAt(null);
                $this->em->flush();

                $this->exceptionNotifier->notifyException(new UserNotifyableException(
                    $automation->getOwnerId(),
                    sprintf('Automation "%s" disabled', $automation->getName()),
                    sprintf('The automation "%s" of the saved search "%s" was disabled: %s.', $automation->getName(), $automation->getSavedSearch()?->getName(), $disableReason),
                ));
            }
        }

        $this->em->flush();
    }

    /**
     * @return bool whether the entity manager was closed (and has been reset)
     */
    private function resetClosedEntityManager(): bool
    {
        if ($this->em->isOpen()) {
            return false;
        }

        $this->managerRegistry->resetManager();

        return true;
    }

    /**
     * @param \ArrayObject<int, AutomationActionState> $states
     */
    private static function normalizeStates(\ArrayObject $states): array
    {
        return array_map(fn (AutomationActionState $state): array => $state->toArray(), $states->getArrayCopy());
    }
}
