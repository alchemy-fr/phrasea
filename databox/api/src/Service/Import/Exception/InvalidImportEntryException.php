<?php

declare(strict_types=1);

namespace App\Service\Import\Exception;

/**
 * An entry of the instructions file cannot be imported: only this entry fails.
 */
final class InvalidImportEntryException extends \RuntimeException
{
}
