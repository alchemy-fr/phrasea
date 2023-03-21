<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;


class RunList extends \ArrayObject
{
    public function mergeWith(self $list): void
    {
        $this->exchangeArray(array_merge($this->getArrayCopy(), $list->getArrayCopy()));
    }
}
