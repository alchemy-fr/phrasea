<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Repository;

use Alchemy\Workflow\Exception\LockException;
use Alchemy\Workflow\State\JobState;
use Alchemy\Workflow\State\WorkflowState;

class FileSystemStateRepository implements LockAwareStateRepositoryInterface
{
    private const WORKFLOW_FILENAME = '__workflow';
    private const JOB_PREFIX = 'job::';
    private string $path;

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

    public function persistWorkflowState(WorkflowState $state): void
    {
        $path = $this->getWorkflowPath($state->getId(), self::WORKFLOW_FILENAME);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755);
        }

        file_put_contents($path, serialize($state));
    }

    public function getJobState(string $workflowId, string $jobId): ?JobState
    {
        $fd = $this->fileDescriptors[$workflowId][$jobId] ?? null;
        if (null === $fd) {
            return $this->readJobState($workflowId, $jobId);
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

    private function readJobState(string $workflowId, string $jobId): ?JobState
    {
        $path = $this->getJobPath($workflowId, $jobId);
        if (file_exists($path)) {
            return unserialize(file_get_contents($path));
        }

        return null;
    }

    public function acquireJobLock(string $workflowId, string $jobId): void
    {
        $path = $this->getJobPath($workflowId, $jobId);
        $fd = fopen($path, 'c+');

        if (!flock($fd, LOCK_EX)) {
            throw new LockException(sprintf('Cannot acquire lock on "%s"', $path));
        }

        $this->fileDescriptors[$workflowId][$jobId] = $fd;
    }

    public function releaseJobLock(string $workflowId, string $jobId): void
    {
        $fd = $this->fileDescriptors[$workflowId][$jobId] ?? null;
        if ($fd) {
            flock($fd, LOCK_UN);
            fclose($fd);
        }

        unset($this->fileDescriptors[$workflowId][$jobId]);
    }

    public function persistJobState(JobState $state): void
    {
        $fd = $this->fileDescriptors[$state->getWorkflowId()][$state->getJobId()] ?? null;
        if (null === $fd) {
            $path = $this->getJobPath($state->getWorkflowId(), $state->getJobId());
            $fd = fopen($path, 'r+');
        }

        ftruncate($fd, 0);
        fseek($fd, 0);
        fwrite($fd, serialize($state));
        fflush($fd);
        flock($fd, LOCK_UN);
    }

    public function removeJobState(string $workflowId, string $jobId): void
    {
        $path = $this->getJobPath($workflowId, $jobId);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function getWorkflowPath(string $id, string $filename): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$filename.'.state';
    }

    private function getJobPath(string $workflowId, string $jobId): string
    {
        return $this->getWorkflowPath($workflowId, self::JOB_PREFIX.$jobId);
    }
}
