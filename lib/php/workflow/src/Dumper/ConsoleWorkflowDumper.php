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

        if (!empty($state->getContext())) {
            $io->section('Context');
            $table = $io->createTable();
            $table->setHeaders([
                'Key', 'Value',
            ]);
            foreach ($state->getContext() as $key => $value) {
                $table->addRow([$key, $value]);
            }
            $table->render();
            $io->newLine();
        }

        $io->section('Steps');

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
                    $jobState = $state->getLastJobState($jobId);

                    $table->addRow([
                        0 === $runIndex ? $stageIndex + 1 : '',
                        0 === $stepIndex ? $jobId : '',
                        $step->getName(),
                        $jobState && $jobState->getStatus() ? self::STATUSES[$jobState->getStatus()] : '-',
                    ]);
                }
            }
        }

        $table->render();
    }
}
