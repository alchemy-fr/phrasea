<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Action;

class ArrayActionRegistry implements ActionRegistryInterface
{
    private array $actions = [];

    public function getAction(string $actionName): ActionInterface
    {
        return $this->actions[$actionName]
            ?? throw new \InvalidArgumentException(sprintf('Action "%s" does not exist', $actionName));
    }
}
