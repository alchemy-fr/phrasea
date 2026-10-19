<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Action;

class ArrayActionRegistry implements ActionRegistryInterface
{
    /**
     * @param array<string, ActionInterface> $actions
     */
    public function __construct(private readonly array $actions = [])
    {
    }

    public function getAction(string $actionName): ActionInterface
    {
        return $this->actions[$actionName]
            ?? throw new \InvalidArgumentException(sprintf('Action "%s" does not exist', $actionName));
    }
}
