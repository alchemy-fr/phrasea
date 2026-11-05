<?php

declare(strict_types=1);

namespace App\Audit;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * A console command acts as the system, under its own correlation ID.
 */
#[AsEventListener(event: ConsoleEvents::COMMAND)]
final readonly class AuditConsoleListener
{
    public function __construct(
        private AuditContext $auditContext,
    ) {
    }

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $this->auditContext->startCommand($event->getCommand()?->getName() ?? 'unknown');
    }
}
