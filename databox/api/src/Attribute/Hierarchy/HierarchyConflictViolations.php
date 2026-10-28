<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Hierarchy conflicts answered as a 422: a violation per conflicting attribute (code CODE, path
 * "hierarchy.<definition ID>"), the client may confirm with the "replace" strategy.
 */
final class HierarchyConflictViolations
{
    public const string CODE = 'hierarchy_conflict';
    public const string PATH_PREFIX = 'hierarchy.';

    /**
     * @param array<array{assetId: string, conflict: HierarchyConflict}> $conflicts
     */
    public static function createException(array $conflicts): ValidationException
    {
        $violations = new ConstraintViolationList();
        foreach ($conflicts as ['conflict' => $conflict]) {
            $violations->add(new ConstraintViolation(
                $conflict->getMessage(),
                null,
                [],
                null,
                self::PATH_PREFIX.$conflict->definition->getId(),
                $conflict->current?->getValue(),
                code: self::CODE,
            ));
        }

        return new ValidationException($violations);
    }
}
