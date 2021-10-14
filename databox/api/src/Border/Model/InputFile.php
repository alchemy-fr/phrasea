<?php

declare(strict_types=1);

namespace App\Border\Model;

class InputFile
{
    private string $name;
    private ?string $type;
    private int $size;

    public function __construct(string $name, ?string $type, int $size)
    {
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
