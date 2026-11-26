<?php

declare(strict_types=1);

namespace App\Service\Asset\Exception;

use Alchemy\Workflow\Exception\JobRefusalExceptionInterface;
use App\Entity\Core\File;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

/**
 * The file must not be read by any processing (rendition build, analysis…):
 * its content is refused or its analysis is not accepted.
 *
 * Not a job skip: a workflow job refused this way ends in failure, with this reason as its error.
 */
final class FileNotProcessableException extends \RuntimeException implements UnrecoverableExceptionInterface, JobRefusalExceptionInterface
{
    public function __construct(File $file, string $reason)
    {
        parent::__construct(sprintf('File "%s" cannot be processed: %s', $file->getId(), $reason));
    }
}
