<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Dumper;

use Alchemy\Workflow\Planner\Plan;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\StateUtil;
use Alchemy\Workflow\State\WorkflowState;
use Symfony\Component\Console\Output\OutputInterface;

class JsonWorkflowDumper implements WorkflowDumperInterface
{
    public function dumpWorkflow(WorkflowState $state, Plan $plan, OutputInterface $output): void
    {
        $stages = [];
        foreach ($plan->getStages() as $stageIndex => $stage) {
            $jobs = [];

            foreach ($stage->getRuns() as $run) {
                $jobId = $run->getJob()->getId();
                $jobState = $state->getJobState($jobId);

                $job = [
                    'id' => $jobId,
                    'name' => $run->getJob()->getName(),
                    'needs' => array_values($run->getJob()->getNeeds()->getArrayCopy()),
                ];

                if ($jobState instanceof JobState) {
                    $job = array_merge($job, [
                        'id' => $jobState->getJobId(),
                        'status' => $jobState->getStatus(),
                        'outputs' => $jobState->getOutputs()->getArrayCopy(),
                        'startedAt' => $jobState->getStartedAt()?->formatAtom(),
                        'endedAt' => $jobState->getEndedAt()?->formatAtom(),
                        'duration' => StateUtil::getFormattedDuration($jobState->getDuration()),
                    ]);
                }

                $jobs[] = $job;
            }

            $stages[] = [
                'stage' => $stageIndex + 1,
                'jobs' => $jobs,
            ];
        }

        $output->write(json_encode([
            'id' => $state->getId(),
            'name' => $state->getWorkflowName(),
            'status' => $state->getStatus(),
            'startedAt' => $state->getStartedAt()->formatAtom(),
            'endedAt' => $state->getEndedAt()?->formatAtom(),
            'stages' => $stages,
            'duration' => StateUtil::getFormattedDuration($state->getDuration()),
        ]));
    }
}
