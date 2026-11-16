<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * The binaries the worker runs for the external tools: with a converter URL, the wrappers of
 * convert-exec installed in the worker image, which run them in the conversion container;
 * without, null (each caller finds the local binary, as before).
 */
final readonly class ConverterTools
{
    public const string WRAPPERS_DIR = '/usr/local/libexec/convert';

    private ?string $converterUrl;

    public function __construct(
        ?string $converterUrl = null,
        private string $wrappersDir = self::WRAPPERS_DIR,
    ) {
        $this->converterUrl = '' === $converterUrl ? null : $converterUrl;
    }

    public function isRemote(): bool
    {
        return null !== $this->converterUrl;
    }

    public function getBinary(Tool $tool): ?string
    {
        return $this->isRemote() ? $this->wrappersDir.'/'.$tool->value : null;
    }

    /**
     * Variables the wrappers need, for the callers that start the tools with an emptied
     * environment (PdfConverter).
     *
     * @return array<string, string>
     */
    public function getEnvironment(): array
    {
        return null !== $this->converterUrl ? ['CONVERTER_URL' => $this->converterUrl] : [];
    }

    /**
     * php-ffmpeg configuration of the binaries.
     *
     * @return array<string, string>
     */
    public function getFFMpegConfiguration(): array
    {
        if (!$this->isRemote()) {
            return [];
        }

        return [
            'ffmpeg.binaries' => $this->getBinary(Tool::Ffmpeg),
            'ffprobe.binaries' => $this->getBinary(Tool::Ffprobe),
        ];
    }
}
