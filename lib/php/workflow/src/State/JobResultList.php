<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

class JobResultList extends \ArrayObject
{
    public function setJobResult(string $jobId, JobState $result): void
    {
        $this->offsetSet($jobId, $result);
    }

    public function getJobResult(string $jobId): JobState
    {
        if ($this->offsetExists($jobId)) {
            return $this->offsetGet($jobId);
        }

        throw new \InvalidArgumentException(sprintf('Result for job "%s" does not exist', $jobId));
    }

    public function hasJobResult(string $jobId): bool
    {
        return $this->offsetExists($jobId);
    }

    public function setJobState(string $jobId, int $state): void
    {
        if (!isset($this[$jobId])) {
            $this[$jobId] = new JobState($state);
        } else {
            $this[$jobId]->setState($state);
        }
    }
}
