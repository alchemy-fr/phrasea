<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

class JobResultList extends \ArrayObject
{
    /**
     * @param JobState[] $array
     */
    public function __construct(array $array)
    {
        $jobs = [];
        foreach ($array as $job) {
            $jobs[$job->getJobId()] = $job;
        }

        parent::__construct($jobs);
    }

    public function setJobResult(JobState $result): void
    {
        $this->offsetSet($result->getJobId(), $result);
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
}
