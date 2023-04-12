<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor;

final class EnvContainer extends \ArrayObject
{
    public function offsetExists(mixed $key): bool
    {
        return parent::offsetExists($key) || false !== getenv($key);
    }

    public function offsetGet(mixed $key): mixed
    {
        if (parent::offsetExists($key)) {
            return parent::offsetGet($key);
        }

        if (false !== $env = getenv($key)) {
            return $env;
        }

        return null;
    }

    public function mergeWith(array $envs): self
    {
        return new self(array_merge($this->getArrayCopy(), $envs));
    }

    public function __get(string $name)
    {
        return $this->offsetGet($name);
    }
}
