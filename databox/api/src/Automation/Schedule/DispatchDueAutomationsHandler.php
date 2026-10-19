<?php

declare(strict_types=1);

namespace App\Automation\Schedule;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DispatchDueAutomationsHandler
{
    public function __construct(
        private AutomationScheduler $scheduler,
    ) {
    }

    public function __invoke(DispatchDueAutomations $message): void
    {
        $this->scheduler->dispatchDue(new \DateTimeImmutable());
    }
}
