<?php

declare(strict_types=1);

namespace App\OperationTask;

/**
 * Thrown when a running task notices that it has been cancelled: the cancellation is
 * cooperative, the task stops at its next progress update.
 */
final class OperationTaskCancelledException extends \RuntimeException
{
    public function __construct(string $taskId)
    {
        parent::__construct(sprintf('Task "%s" has been cancelled', $taskId));
    }
}
