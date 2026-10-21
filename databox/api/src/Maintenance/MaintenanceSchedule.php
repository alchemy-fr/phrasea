<?php

declare(strict_types=1);

namespace App\Maintenance;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Every periodic maintenance task of databox, consumed by the single-replica
 * "databox-scheduler" container through the "scheduler_maintenance" transport.
 * Hashed expressions (#hourly, #midnight) spread the tasks over the period.
 */
#[AsSchedule('maintenance')]
final readonly class MaintenanceSchedule implements ScheduleProviderInterface
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
            ->add(
                RecurringMessage::cron('#hourly', new RunCommandMessage('alchemy:notifier:digest:flush')),
                RecurringMessage::cron('#hourly', new RunCommandMessage('app:integration:renew-tokens')),
                RecurringMessage::cron('#hourly', new RunCommandMessage('app:file:unblock-analyses')),
                RecurringMessage::cron('#midnight', new RunCommandMessage('app:trash:empty')),
            )
            ->lock($this->lockFactory->createLock('maintenance-schedule'))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
