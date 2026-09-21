<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Exception;

/**
 * The workflow state no longer exists (e.g. removed by a cascade when the
 * object the workflow was running on was deleted) while jobs were still in flight.
 */
final class WorkflowStateNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $workflowId, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Workflow state "%s" does not exist', $workflowId), 0, $previous);
    }
}
