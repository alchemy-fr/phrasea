<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Dumper;

use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleWorkflowDumper implements WorkflowDumperInterface
{
    private const STATUSES = [
        JobState::STATUS_FAILURE => '❌',
        JobState::STATUS_SKIPPED => '⏩',
        JobState::STATUS_TRIGGERED => '⌛',
        JobState::STATUS_RUNNING => '⚙️',
        JobState::STATUS_SUCCESS => '✅',
    ];

    public function dumpWorkflow(WorkflowState $state, Plan $plan, OutputInterface $output): void
    {
        $input = new ArrayInput([]);
        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Workflow <info>%s</info>', $state->getId()));

        $table = $io->createTable();
        $table->setHeaders([
            'Stage', 'Job', 'Step', 'Status',
        ]);

        foreach ($plan->getStages() as $stageIndex => $stage) {
            if ($stageIndex > 0) {
                $table->addRow(new TableSeparator());
            }

            foreach ($stage->getRuns() as $runIndex => $run) {
                foreach ($run->getJob()->getSteps() as $stepIndex => $step) {
                    $jobId = $run->getJob()->getId();
                    $jobState = $state->getJobState($jobId);

                    $table->addRow([
                        $runIndex === 0 ? $stageIndex + 1 : '',
                        $stepIndex === 0 ? $jobId : '',
                        $step->getName(),
                        $jobState->getStatus() ? self::STATUSES[$jobState->getStatus()] : '-',
                    ]);
                }
            }
        }

        $table->render();
    }
}
