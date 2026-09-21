<?php

declare(strict_types=1);

namespace App\Border\Exception;

/**
 * Raised when a file source is not an HTTP(S) URL — typically a Windows share
 * path ("U:\...") pasted into an import list. Retrying cannot help: the value
 * itself has to be fixed, so callers log it and drop the message.
 */
final class UnsupportedUriException extends \InvalidArgumentException
{
    public function __construct(public readonly string $uri)
    {
        parent::__construct(\sprintf('"%s" is not an HTTP(S) URL and cannot be downloaded', $uri));
    }
}
