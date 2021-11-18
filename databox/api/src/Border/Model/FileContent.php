<?php

declare(strict_types=1);

namespace App\Border\Model;

class FileContent
{
    private InputFile $file;
    private string $path;

    public function __construct(InputFile $file, string $path)
    {
        $this->file = $file;
        $this->path = $path;
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
