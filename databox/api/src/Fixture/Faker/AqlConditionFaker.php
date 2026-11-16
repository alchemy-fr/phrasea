<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Faker\Provider\Base as BaseProvider;

/**
 * Builds the AQL conditions of the fixtures, which reference objects by ID: Alice cannot
 * interpolate a reference in a string starting with "@" (it is lexed as a reference).
 */
class AqlConditionFaker extends BaseProvider
{
    public function aqlAttributeIsNot(string $attributeSlug, string $value): string
    {
        return sprintf('%s IS NOT "%s"', $attributeSlug, $value);
    }
}
