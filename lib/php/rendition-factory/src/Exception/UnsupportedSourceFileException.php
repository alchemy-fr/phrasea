<?php

namespace Alchemy\RenditionFactory\Exception;

/**
 * Raised when the source file cannot be decoded by the underlying tool — a
 * mislabelled MIME type, a truncated upload, or a format the image/video stack
 * has no delegate for.
 *
 * Retrying cannot help: the file has to be replaced, so callers skip the job
 * instead of re-queueing it.
 */
class UnsupportedSourceFileException extends \RuntimeException
{
    public function __construct(string $path, ?string $type, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Cannot decode source file "%s" (type: %s)', basename($path), $type ?? 'unknown'),
            0,
            $previous
        );
    }
}
