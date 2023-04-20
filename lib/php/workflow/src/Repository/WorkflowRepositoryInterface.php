<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Repository;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\Workflow;

interface WorkflowRepositoryInterface
{
    public function loadWorkflowByName(string $name): Workflow;

    /**
     * @return Workflow[]
     */
    public function getWorkflowsByEvent(WorkflowEvent $event): array;

    public function loadAll(): void;
}
