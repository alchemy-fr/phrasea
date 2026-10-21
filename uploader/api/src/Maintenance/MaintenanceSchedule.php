<?php

declare(strict_types=1);

namespace App\Maintenance;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Every periodic maintenance task of the uploader, consumed by the single-replica
 * "uploader-scheduler" container through the "scheduler_maintenance" transport.
 * No lock: the single replica is the guarantee, and the tasks are idempotent.
 * The hashed expression (#midnight) spreads the task between 00:00 and 02:59.
 */
#[AsSchedule('maintenance')]
final readonly class MaintenanceSchedule implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(
                RecurringMessage::cron('#midnight', new RunCommandMessage('app:asset:clean-expired')),
            )
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
