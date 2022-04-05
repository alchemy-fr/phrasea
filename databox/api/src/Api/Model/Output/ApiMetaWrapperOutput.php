<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Traversable;

class ApiMetaWrapperOutput implements \IteratorAggregate
{
    private Traversable $result;
    private array $meta = [];

    public function __construct(Traversable $result)
    {
        $this->result = $result;
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

    public function getIterator(): Traversable
    {
        return $this->result;
    }
}
