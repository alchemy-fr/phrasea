<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Automation\Schedule\DispatchDueAutomations;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Every periodic task of databox, consumed by the single-replica
 * "databox-scheduler" container through the "scheduler_main" transport.
 * The tasks run one after the other in that process: a long one delays the
 * automation triggers, which catch up on the due automations afterwards.
 * Hashed expressions (#hourly, #midnight) spread the tasks over the period.
 * The lock is a safety net when two scheduler containers overlap during a rolling deployment,
 * the state keeps the missed occurrences across restarts.
 */
#[AsSchedule('main')]
final readonly class MainSchedule implements ScheduleProviderInterface
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
                RecurringMessage::every('1 minute', new DispatchDueAutomations()),
                RecurringMessage::cron('#hourly', new RunCommandMessage('alchemy:notifier:digest:flush')),
                RecurringMessage::cron('#hourly', new RunCommandMessage('app:integration:renew-tokens')),
                RecurringMessage::cron('#hourly', new RunCommandMessage('app:file:unblock-analyses')),
                RecurringMessage::cron('#midnight', new RunCommandMessage('app:trash:empty')),
            )
            ->lock($this->lockFactory->createLock('main-schedule'))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
