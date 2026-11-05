<?php

declare(strict_types=1);

namespace App\Service\Import\Exception;

/**
 * The instructions file cannot be read at all: the whole import fails.
 */
final class ImportFileException extends \RuntimeException
{
}
