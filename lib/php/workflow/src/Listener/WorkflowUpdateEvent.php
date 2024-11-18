<?php

namespace Alchemy\Workflow\Listener;

use Alchemy\Workflow\State\WorkflowState;

readonly class WorkflowUpdateEvent
{
    public function __construct(
        private WorkflowState $state,
    ) {
    }

    public function getState(): WorkflowState
    {
        return $this->state;
    }
}
