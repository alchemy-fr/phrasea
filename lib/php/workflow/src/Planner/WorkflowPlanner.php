<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\Workflow;

final class WorkflowPlanner
{
    /**
     * @var Workflow[]
     */
    private array $workflows;

    /**
     * @param Workflow[] $workflows
     */
    public function __construct(array $workflows)
    {
        $this->workflows = $workflows;
    }

    public function planEvent(WorkflowEvent $event): Plan
    {
        $stages = new StageList();
        foreach ($this->workflows as $workflow) {
            if ($workflow->getOn()->hasEventName($event->getName())) {
                $stages = $stages->mergeWithCopy(
                    $this->createStages($workflow, $workflow->getJobIds())
                );
            }
        }

        return new Plan($stages);
    }

    public function planAll(): Plan
    {
        $stages = new StageList();
        foreach ($this->workflows as $workflow) {
            $stages = $stages->mergeWithCopy(
                $this->createStages($workflow, $workflow->getJobIds())
            );
        }

        return new Plan($stages);
    }

    private function createStages(Workflow $workflow, array $jobIds): StageList
    {
        $jobDependencies = [];
        while (!empty($jobIds)) {
            $discoveredJobs = [];
            foreach ($jobIds as $jobId) {
                if (!isset($jobDependencies[$jobId])) {
                    $job = $workflow->getJob($jobId);
                    $jobDependencies[$jobId] = $job->getNeeds()->getArrayCopy();

                    $discoveredJobs = array_merge($discoveredJobs, $jobDependencies[$jobId]);
                }
            }
            $jobIds = $discoveredJobs;
        }


        $stages = new StageList();
        $i = 0;
        while (!empty($jobDependencies)) {
            $stage = new Stage();
            $runs = $stage->getRuns();

            $iterator = $jobDependencies;
            foreach ($iterator as $jobId => $dependencies) {
                if ($this->jobsArePresentInStages($stages, $dependencies)) {
                    $runs->append(new Run($workflow, $workflow->getJob($jobId)));
                    unset($jobDependencies[$jobId]);
                }
            }

            if (empty($runs)) {
                throw new \RuntimeException(sprintf('Unable to build stage %d: empty runs', $stages->count() + 1));
            }
            ++$i;

            $stages->append($stage);
        }

        return $stages;
    }

    private function jobsArePresentInStages(StageList $stages, array $jobIds): bool
    {
        foreach ($jobIds as $jobId) {
            if (!$stages->containsJobId($jobId)) {
                return false;
            }
        }

        return true;
    }
}
