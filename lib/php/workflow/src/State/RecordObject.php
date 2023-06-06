<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

abstract class RecordObject extends \ArrayObject implements \JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        if (empty($this->getArrayCopy())) {
            return new \stdClass();
        }

        return $this->getArrayCopy();
    }
}
