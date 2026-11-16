<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * An AQL condition that cannot be turned into a query because of its own content
 * (operands, function arguments…): answered with a 400, its message is shown to the user.
 */
final class InvalidAQLException extends BadRequestHttpException
{
}
