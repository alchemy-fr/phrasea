<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Validator;

use Alchemy\Workflow\Event\WorkflowEvent;
use Alchemy\Workflow\Model\OnEvent;

interface EventValidatorInterface
{
    public function validateEvent(OnEvent $onEvent, WorkflowEvent $event): void;
}
