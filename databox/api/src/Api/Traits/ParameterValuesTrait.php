<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;

/**
 * Reads the query parameters declared on the operation, as cast and validated by API Platform.
 *
 * This replaces `$context['filters']`, the raw query string, as the source of the search
 * options: an undeclared key never reaches a search.
 */
trait ParameterValuesTrait
{
    /**
     * @return array<string, mixed> parameter key => value, for the parameters present in the request
     */
    protected static function getParameterValues(Operation $operation): array
    {
        $values = [];
        foreach ($operation->getParameters() ?? [] as $key => $parameter) {
            // `x[]` is the hydra:search twin of `x` and carries the same value
            if (str_ends_with((string) $key, '[]')) {
                continue;
            }

            $value = $parameter->getValue();
            if ($value instanceof ParameterNotFound) {
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    protected static function getParameterValue(Operation $operation, string $key, mixed $default = null): mixed
    {
        $value = $operation->getParameters()?->get($key)?->getValue();
        if (null === $value || $value instanceof ParameterNotFound) {
            return $default;
        }

        return $value;
    }
}
