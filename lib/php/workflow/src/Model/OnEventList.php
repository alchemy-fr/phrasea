<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class OnEventList extends \ArrayObject
{
    public function hasEventName(string $eventName): bool
    {
        return $this->offsetExists($eventName);
    }
}
