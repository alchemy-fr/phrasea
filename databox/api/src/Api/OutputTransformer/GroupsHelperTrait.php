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

        foreach ($groups as $group) {
            if (in_array($group, $actual, true)) {
                return true;
            }
        }

        return false;
    }
}
