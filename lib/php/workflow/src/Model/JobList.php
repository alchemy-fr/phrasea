<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class JobList extends \ArrayObject
{
    public function append($value): void
    {
        throw new \LogicException('Cannot be called');
    }
}
