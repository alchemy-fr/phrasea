<?php

declare(strict_types=1);

namespace App\Service\Asset\Exception;

use App\Entity\Core\File;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

/**
 * The content downloaded from the URL of the file differs from the one accepted
 * by the content check (forbidden, not allowed or of another type).
 */
final class RefusedFileContentException extends \RuntimeException implements UnrecoverableExceptionInterface
{
    public function __construct(File $file, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Content downloaded for file "%s" is refused: %s', $file->getId(), $reason), 0, $previous);
    }
}
