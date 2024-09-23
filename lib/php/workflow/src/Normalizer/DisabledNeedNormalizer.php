<?php

namespace Alchemy\Workflow\Normalizer;

use Alchemy\Workflow\Model\Job;
use Alchemy\Workflow\Model\Workflow;

final readonly class DisabledNeedNormalizer
{
    public function normalizeWorkflow(Workflow $workflow): void
    {
        foreach ($workflow->getJobs() as $job) {
            $this->normalizeJob($workflow, $job);
        }
    }

    private function normalizeJob(Workflow $workflow, Job $job): void
    {
        if ($job->isDisabled()) {
            return;
        }

        foreach ($job->getNeeds() as $need) {
            $neededJob = $workflow->getJob($need);

            $this->normalizeJob($workflow, $neededJob);

            if ($neededJob->isDisabled()) {
                $job->markDisabled('Dependency is disabled');
            }
        }
    }
}
