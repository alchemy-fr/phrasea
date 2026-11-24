<?php

declare(strict_types=1);

namespace App\Service\Asset\Exception;

use App\Entity\Core\File;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

/**
 * The content fetched from the URL of the file is refused (type not allowed,
 * inconsistent with the declared one or forbidden): retrying never helps.
 */
final class FileImportRefusedException extends \RuntimeException implements UnrecoverableExceptionInterface
{
    public function __construct(File $file, \Throwable $previous)
    {
        parent::__construct(sprintf('Import of file "%s" refused: %s', $file->getId(), $previous->getMessage()), 0, $previous);
    }
}
