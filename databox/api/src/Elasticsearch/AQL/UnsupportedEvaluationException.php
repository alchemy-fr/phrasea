<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

/**
 * A condition, or a value of the document, that {@see AQLDocumentEvaluator} cannot evaluate exactly
 * as Elasticsearch would: the caller must deny rather than guess.
 */
final class UnsupportedEvaluationException extends \RuntimeException
{
}
