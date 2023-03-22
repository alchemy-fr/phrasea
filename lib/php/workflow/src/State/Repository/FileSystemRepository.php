<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\Exception\LockException;
use Alchemy\Workflow\State\JobResultList;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

class FileSystemRepository implements StateRepositoryInterface
{
    const WORKFLOW_FILENAME = '__workflow';
    const JOB_PREFIX = 'job::';
    private string $path;

    private array $fileDescriptors = [];

    public function __construct(string $path)
    {
        if (!is_dir($path)) {
            throw new \Exception(sprintf('Directory "%s" does not exist', $path));
        }
        if (!is_writable($path)) {
            throw new \Exception(sprintf('Directory "%s" is not writable', $path));
        }
        $this->path = $path;
    }

    public function getWorkflowState(string $id): WorkflowState
    {
        $path = $this->getWorkflowPath($id, self::WORKFLOW_FILENAME);
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('Workflow state "%s" does not exist', $id));
        }

        return unserialize(file_get_contents($path));
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $path = $this->getWorkflowPath($state->getId(), self::WORKFLOW_FILENAME);

        file_put_contents($path, serialize($state));
    }

    public function getJobState(string $workflowId, string $jobId): ?JobState
    {
        $path = $this->getJobPath($workflowId, $jobId);
        if (!file_exists($path)) {
            return null;
        }

        return unserialize(file_get_contents($path));
    }

    public function acquireJobLock(string $workflowId, string $jobId): void
    {
        $path = $this->getJobPath($workflowId, $jobId);
        $this->fileDescriptors[$workflowId][$jobId] = $fd = fopen($path, 'r+');

        if (!flock($fd, LOCK_EX)) {
            throw new LockException('Cannot acquire lock on "%s"', $path);
        }
    }

    private function getLock(string $workflowId, string $jobId)
    {
        $this->acquireJobLock($workflowId, $jobId);

        return $this->fileDescriptors[$workflowId][$jobId];
    }

    public function persistJobState(JobState $state): void
    {
        $fd = $this->fileDescriptors[$state->getWorkflowId()][$state->getJobId()] ?? null;

        if (null === $fd) {
            $fd = $this->getLock($state->getWorkflowId(), $state->getJobId());
        }

        ftruncate($fd, 0);
        fwrite($fd, serialize($state));
        fflush($fd);
        flock($fd, LOCK_UN);

        fclose($fd);
    }

    public function getJobResultList(string $workflowId): JobResultList
    {
        $dir = dirname($this->getWorkflowPath($workflowId, ''));
        $files = scandir($dir);

        /** @var JobState[] $jobs */
        $jobs = [];
        foreach ($files as $file) {
            if (0 === strpos($file, self::JOB_PREFIX)) {
                $jobs[] = unserialize(file_get_contents($dir.DIRECTORY_SEPARATOR.$file));
            }
        }

        return new JobResultList($jobs);
    }

    private function getWorkflowPath(string $id, string $filename): string
    {
        if (!is_dir($this->path.DIRECTORY_SEPARATOR.$id)) {
            mkdir($this->path.DIRECTORY_SEPARATOR.$id, 0755);
        }

        return $this->path.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$filename.'.state';
    }

    private function getJobPath(string $workflowId, string $jobId): string
    {
        return $this->getWorkflowPath($workflowId, self::JOB_PREFIX.$jobId);
    }
}
