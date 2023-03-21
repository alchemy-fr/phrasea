<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class RunList extends \ArrayObject
{
    public function addDependencies(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->append($job);
        }
    }

    public function mergeWith(self $list): self
    {
        return new self(array_merge($this->getArrayCopy(), $list->getArrayCopy()));
    }
}
