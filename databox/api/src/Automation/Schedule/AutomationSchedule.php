<?php

declare(strict_types=1);

namespace App\Automation\Schedule;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Consumed by the single-replica "databox-scheduler" container through the "scheduler_automation" transport.
 * The lock is a safety net when two scheduler containers overlap during a rolling deployment,
 * the state keeps the missed occurrences across restarts.
 */
#[AsSchedule('automation')]
final readonly class AutomationSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private LockFactory $lockFactory,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every('1 minute', new DispatchDueAutomations()))
            ->lock($this->lockFactory->createLock('automation-schedule'))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
