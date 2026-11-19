<?php

declare(strict_types=1);

namespace App\Service\Asset\Exception;

use App\Entity\Core\File;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

/**
 * The URL of the file no longer serves any content (removed at the source):
 * retrying never helps.
 */
final class RemoteFileNotFoundException extends \RuntimeException implements UnrecoverableExceptionInterface
{
    public function __construct(File $file, \Throwable $previous)
    {
        parent::__construct(sprintf('Source of file "%s" no longer exists: %s', $file->getId(), $previous->getMessage()), 0, $previous);
    }
}
