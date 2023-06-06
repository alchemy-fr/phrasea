<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

final class Outputs extends RecordObject
{
    public function set(string $key, $value): void
    {
        if ($this->offsetExists($key)) {
            throw new \InvalidArgumentException(sprintf('Output "%s" is already defined', $key));
        }

        $this->offsetSet($key, $value);
    }

    public function __get(string $name)
    {
        if ($this->offsetExists($name)) {
            return $this->offsetGet($name);
        }

        return null;
    }
}
