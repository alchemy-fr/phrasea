<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

class ApiMetaWrapperOutput
{
    private iterable $result;
    private array $meta = [];

    public function __construct(iterable $result)
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
}
