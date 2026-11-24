<?php

declare(strict_types=1);

namespace App\Elasticsearch\Exception;

/**
 * The physical index built by a populate is missing, or is not the one the populate
 * created (e.g. recreated by Elasticsearch with a dynamic mapping by a write that came
 * after its deletion): the alias must not be switched to it.
 */
final class PopulateIndexNotReadyException extends \RuntimeException
{
}
