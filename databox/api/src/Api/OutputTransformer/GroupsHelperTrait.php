<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

trait GroupsHelperTrait
{
    protected function hasGroup(string|array $group, array $context): bool
    {
        $groups = is_array($group) ? $group : [$group];
        $actual = $context['groups'] ?? [];
        if (empty($actual)) {
            return false;
        }

        return array_any($groups, fn ($group) => in_array($group, $actual, true));
    }
}
