<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class WorkflowList extends \ArrayObject
{
    public function getByName(string $name): Workflow
    {
        /** @var Workflow[] $this */
        foreach ($this as $workflow) {
            if ($workflow->getName() === $name) {
                return $workflow;
            }
        }

        throw new \InvalidArgumentException(sprintf('Workflow "%s" not found', $name));
    }

    /**
     * @return Workflow[]
     */
    public function getByEventName(string $eventName): array
    {
        return array_filter($this->getArrayCopy(), fn (Workflow $workflow) => $workflow->getOn()->hasEventName($eventName));
    }
}
