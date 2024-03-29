<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\MergeableValueObjectInterface;

abstract class AbstractOptions implements \JsonSerializable, MergeableValueObjectInterface
{
    final public function __construct(?array $options = null)
    {
        if (null !== $options) {
            $this->fromJson($options);
        }
    }

    abstract public function fromJson(array $options): void;

    public function mergeWith(MergeableValueObjectInterface $object): MergeableValueObjectInterface
    {
        return new static(array_merge(array_filter($this->jsonSerialize()), array_filter($object->jsonSerialize())));
    }
}
