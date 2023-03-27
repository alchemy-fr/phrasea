<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Model\Workflow;

interface WorkflowRepositoryInterface
{
    public function loadWorkflowByName(string $name): Workflow;
}
