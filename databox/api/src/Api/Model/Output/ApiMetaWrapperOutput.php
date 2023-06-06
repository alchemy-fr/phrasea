<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

class ApiMetaWrapperOutput implements \IteratorAggregate
{
    private array $meta = [];

    public function __construct(private readonly \Traversable $result)
    {
    }

    public function getResult(): iterable
    {
        return $this->result;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta($key, $value): void
    {
        $this->meta[$key] = $value;
    }

    public function getIterator(): \Traversable
    {
        return $this->result;
    }
}
