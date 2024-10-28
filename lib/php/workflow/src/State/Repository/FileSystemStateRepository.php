<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\Exception\LockException;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

class FileSystemStateRepository implements LockAwareStateRepositoryInterface
{
    private const string WORKFLOW_FILENAME = '__workflow';
    private const string JOB_PREFIX = 'job::';
    private readonly string $path;

    private array $fileDescriptors = [];

    public function __construct(string $path)
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755)) {
                throw new \Exception(sprintf('Cannot create directory "%s"', $path));
            }
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

        /** @var WorkflowState $state */
        $state = unserialize(file_get_contents($path));
        $state->setStateRepository($this);

        return $state;
    }

    public function getJobState(string $workflowId, string $jobStateId): ?JobState
    {
        $fd = $this->fileDescriptors[$workflowId][$jobStateId] ?? null;
        if (null === $fd) {
            return $this->readJobState($workflowId, $jobStateId);
        }

        fseek($fd, 0);
        $content = '';
        while (!feof($fd)) {
            $content .= fgets($fd, 4096);
        }

        if (empty($content)) {
            return null;
        }

        return unserialize($content);
    }

    public function getLastJobState(string $workflowId, string $jobId): ?JobState
    {
        $jobStateId = $this->getStateId($jobId);

        return $this->getJobState($workflowId, $jobStateId);
    }

    public function createJobState(string $workflowId, string $jobId): JobState
    {
        return new JobState($workflowId, $jobId, id: $this->getStateId($jobId));
    }

    public function persistWorkflowState(WorkflowState $state): void
    {
        $path = $this->getWorkflowPath($state->getId(), self::WORKFLOW_FILENAME);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755);
        }

        file_put_contents($path, serialize($state));
    }

    private function getStateId(string $jobId): string
    {
        return sprintf('%s-0', $jobId);
    }

    public function getJobStates(string $workflowId, string $jobId): array
    {
        $jobStateId = $this->getStateId($jobId);
        $state = $this->getJobState($workflowId, $jobStateId);
        if (null === $state) {
            return [];
        }

        return [$state];
    }

    private function readJobState(string $workflowId, string $jobStateId): ?JobState
    {
        $path = $this->getJobPath($workflowId, $jobStateId);
        if (file_exists($path)) {
            return unserialize(file_get_contents($path));
        }

        return null;
    }

    public function acquireJobLock(string $workflowId, string $jobStateId): void
    {
        $path = $this->getJobPath($workflowId, $jobStateId);
        $fd = fopen($path, 'c+');

        if (!flock($fd, LOCK_EX)) {
            throw new LockException(sprintf('Cannot acquire lock on "%s"', $path));
        }

        $this->fileDescriptors[$workflowId][$jobStateId] = $fd;
    }

    public function releaseJobLock(string $workflowId, string $jobStateId): void
    {
        $fd = $this->fileDescriptors[$workflowId][$jobStateId] ?? null;
        if ($fd) {
            flock($fd, LOCK_UN);
            fclose($fd);
        }

        unset($this->fileDescriptors[$workflowId][$jobStateId]);
    }

    public function persistJobState(JobState $state): void
    {
        $jobStateId = $state->getId();
        $fd = $this->fileDescriptors[$state->getWorkflowId()][$jobStateId] ?? null;
        $hadLock = null === $fd;
        if ($hadLock) {
            $path = $this->getJobPath($state->getWorkflowId(), $jobStateId);
            $fd = fopen($path, 'c+');
        }

        ftruncate($fd, 0);
        fseek($fd, 0);
        fwrite($fd, serialize($state));
        fflush($fd);
        if ($hadLock) {
            flock($fd, LOCK_UN);
        }
    }

    public function removeJobState(string $workflowId, string $jobStateId): void
    {
        $path = $this->getJobPath($workflowId, $jobStateId);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function resetJobState(string $workflowId, string $jobId): void
    {
        $this->removeJobState($workflowId, $jobId);
    }

    private function getWorkflowPath(string $id, string $filename): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$filename.'.state';
    }

    private function getJobPath(string $workflowId, string $jobStateId): string
    {
        return $this->getWorkflowPath($workflowId, self::JOB_PREFIX.$jobStateId);
    }
}
