<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle\Executor\Adapter\Action;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\Action\ActionRegistryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ServiceActionRegistry implements ActionRegistryInterface
{
    public function __construct(private readonly ServiceLocator $services)
    {
    }

    public function getAction(string $actionName): ActionInterface
    {
        return $this->services->get($actionName);
    }
}
