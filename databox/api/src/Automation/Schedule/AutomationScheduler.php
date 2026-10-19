<?php

declare(strict_types=1);

namespace App\Automation\Schedule;

use App\Automation\AutomationRunner;
use App\Entity\Automation\SavedSearchAutomation;
use App\Model\AutomationTriggerEnum;
use App\Repository\Automation\SavedSearchAutomationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Triggers the scheduled automations which are due.
 */
final readonly class AutomationScheduler
{
    public function __construct(
        private SavedSearchAutomationRepository $repository,
        private EntityManagerInterface $em,
        private CronHelper $cronHelper,
        private AutomationRunner $runner,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return SavedSearchAutomation[] the triggered automations
     */
    public function dispatchDue(\DateTimeImmutable $now): array
    {
        $triggered = [];
        $ids = array_map(fn (SavedSearchAutomation $automation): string => $automation->getId(), $this->repository->findDue($now));
        foreach ($ids as $id) {
            // Reloaded: a run executed synchronously clears the entity manager
            $automation = $this->em->find(SavedSearchAutomation::class, $id);
            if (!$automation instanceof SavedSearchAutomation) {
                continue;
            }

            try {
                // Moved forward first: a failing dispatch must not trigger the automation in a loop
                $automation->setNextRunAt($this->cronHelper->getNextRunAt((string) $automation->getSchedule(), $automation->getTimezone(), $now));
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Invalid schedule of automation "%s": %s', $automation->getId(), $e->getMessage()), [
                    'exception' => $e,
                ]);
                $automation->setNextRunAt(null);
                $this->em->flush();

                continue;
            }
            $this->em->flush();

            $this->runner->trigger($automation, AutomationTriggerEnum::Schedule, null);
            $triggered[] = $automation;
        }

        return $triggered;
    }
}
