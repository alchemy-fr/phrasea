<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;

use Alchemy\Workflow\Model\Plan;
use Alchemy\Workflow\Model\Run;
use Alchemy\Workflow\Model\Stage;
use Alchemy\Workflow\Model\StageList;
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

    public function planAll(): Plan
    {
        $stages = new StageList();
        foreach ($this->workflows as $workflow) {
            $stages = $stages->mergeWith(
                $this->createStages($workflow, $workflow->getJobIds())
            );
        }

        $plan = new Plan($stages);

        return $plan;
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
