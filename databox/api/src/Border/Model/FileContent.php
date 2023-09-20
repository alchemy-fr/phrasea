<?php

declare(strict_types=1);

namespace App\Border\Model;

final readonly class FileContent
{
    public function __construct(private InputFile $file, private string $path)
    {
    }

    public function getFile(): InputFile
    {
        return $this->file;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
