<?php

declare(strict_types=1);

namespace App\Service\Clip;

final readonly class ClipResult
{
    public function __construct(
        public string $path,
        public string $mimeType,
        /** The extension of the produced file, null when the source is returned as is */
        public ?string $extension,
        public bool $transformed,
    ) {
    }

    /**
     * Gives the original name of the file the extension of the produced file.
     */
    public function renameFile(?string $name): ?string
    {
        if (!$this->transformed || null === $this->extension || null === $name || '' === $name) {
            return $name;
        }

        $base = pathinfo($name, PATHINFO_FILENAME);

        return ('' !== $base ? $base : 'file').'.'.$this->extension;
    }
}
