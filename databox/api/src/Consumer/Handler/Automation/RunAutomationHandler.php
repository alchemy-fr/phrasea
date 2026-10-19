<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Automation;

use App\Automation\AutomationRunner;
use App\Entity\Automation\AutomationRun;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RunAutomationHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AutomationRunner $runner,
    ) {
    }

    public function __invoke(RunAutomation $message): void
    {
        $run = $this->em->find(AutomationRun::class, $message->runId);
        if (!$run instanceof AutomationRun) {
            // The automation was deleted
            return;
        }

        $this->runner->run($run);
    }
}
