<?php

declare(strict_types=1);

namespace App\Border\Model;

class InputFile
{
    private string $name;
    private ?string $type;
    private int $size;
    private string $url;

    public function __construct(string $name, ?string $type, int $size, string $url)
    {
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->url = $url;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION) ?? '');
    }

    public function getExtensionWithDot(): string
    {
        $ext = $this->getExtension();
        if (!empty($ext)) {
            return '.'.$ext;
        }

        return '';
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
