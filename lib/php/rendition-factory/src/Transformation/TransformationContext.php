<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformation;

use Alchemy\RenditionFactory\File\InputFile;

final class TransformationContext {
    private $files = [];

    public function __construct(
        private array $normalizationOptions,
        private string $workingDirectory,
        private bool $debug = false,
    )
    {
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function createFilePath(): string
    {
        return $this->files[] = tempnam($this->workingDirectory, '');
    }

    public function __destruct()
    {
        if (!$this->debug) {
            array_map('unlink', $this->files);
        }
    }

    public function normalizeFile(InputFile $file, array $supportedFormats): string
    {
        if (!in_array($file->getType(), $supportedFormats)) {
            // TODO convert file

            if ($this->normalizationOptions) {

            }

            return 'new_file.jpg';
        }

        return $file;
    }
}

