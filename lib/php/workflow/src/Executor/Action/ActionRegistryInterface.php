<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Action;

interface ActionRegistryInterface
{
    public function getAction(string $actionName): ActionInterface;
}
