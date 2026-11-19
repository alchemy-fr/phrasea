<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Exception;

final class DownloadTooLargeException extends \RuntimeException
{
    public function __construct(string $uri, int $maxSize, ?int $declaredSize = null)
    {
        parent::__construct(null !== $declaredSize
            ? sprintf('Refusing to download "%s": its size of %d bytes exceeds the maximum of %d bytes', $uri, $declaredSize, $maxSize)
            : sprintf('Download of "%s" interrupted: its content exceeds the maximum of %d bytes', $uri, $maxSize)
        );
    }
}
