<?php

declare(strict_types=1);

namespace App\Border\Model;

class FileContent
{
    public function __construct(private readonly InputFile $file, private readonly string $path)
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
