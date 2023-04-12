<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

final class Inputs extends \ArrayObject
{
    public function mergeWith(array $inputs): self
    {
        return new self(array_merge($this->getArrayCopy(), $inputs));
    }

    public function __get(string $name)
    {
        if ($this->offsetExists($name)) {
            return $this->offsetGet($name);
        }

        return null;
    }
}
