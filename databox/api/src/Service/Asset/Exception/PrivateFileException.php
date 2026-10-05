<?php

declare(strict_types=1);

namespace App\Service\Asset\Exception;

use Alchemy\Workflow\Exception\JobSkipExceptionInterface;
use App\Entity\Core\File;

/**
 * The file lives at a URL the platform is not allowed to fetch
 * (FileSourceInput::isPrivate): any job needing its content must be skipped.
 */
final class PrivateFileException extends \RuntimeException implements JobSkipExceptionInterface
{
    public function __construct(File $file, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('File "%s" has a private path and cannot be fetched', $file->getId()), 0, $previous);
    }

    public function shouldSkipJob(): bool
    {
        return true;
    }
}
