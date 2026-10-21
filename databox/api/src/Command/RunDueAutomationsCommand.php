<?php

declare(strict_types=1);

namespace App\Command;

use App\Automation\Schedule\AutomationScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * What the "automation" schedule does every minute, for development.
 */
#[AsCommand('app:automation:run-due', 'Triggers the scheduled automations which are due')]
class RunDueAutomationsCommand extends Command
{
    public function __construct(
        private readonly AutomationScheduler $scheduler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $triggered = $this->scheduler->dispatchDue(new \DateTimeImmutable());
        foreach ($triggered as $automation) {
            $output->writeln(sprintf('Triggered automation "%s" (%s)', $automation->getName(), $automation->getId()));
        }
        $output->writeln(sprintf('%d automation(s) triggered', count($triggered)));

        return Command::SUCCESS;
    }
}
