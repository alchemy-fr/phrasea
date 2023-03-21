<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State\Provider;

use Alchemy\Workflow\State\WorkflowState;

interface StateRepositoryInterface
{
    /**
     * @throws \InvalidArgumentException if state does not exist
     */
    public function getWorkflow(string $id): WorkflowState;

    public function persistWorkflow(WorkflowState $state): void;
}
