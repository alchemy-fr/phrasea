<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Model\Workflow;
use Alchemy\Workflow\Model\WorkflowList;

class MemoryWorkflowRepository implements WorkflowRepositoryInterface
{
    private WorkflowList $workflows;

    public function __construct(WorkflowList $workflows)
    {
        $this->workflows = $workflows;
    }

    public function loadWorkflowByName(string $name): Workflow
    {
        return $this->workflows->getByName($name);
    }

}
