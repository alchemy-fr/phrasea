<?php

declare(strict_types=1);

namespace App\Form\Builder;

use App\Form\Builder\Constraint\DataboxValues;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

final class FieldConstraintFactory
{
    private const string DATE_TIME_PATTERN = '#^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$#';

    /**
     * @return Constraint[]
     */
    public static function create(FieldDefinition $field, ?string $workspaceId): array
    {
        $constraints = [];
        if ($field->required && !$field->hidden) {
            // A required boolean must be checked: false is blank
            $constraints[] = new Assert\NotBlank();
        }

        $item = self::itemConstraints($field);
        if ($field->multiple) {
            $constraints[] = new Assert\Type('array');
            if ([] !== $item) {
                $constraints[] = new Assert\All($item);
            }
        } else {
            array_push($constraints, ...$item);
        }

        if (FieldType::Entity === $field->type && null !== $field->entityListId) {
            $constraints[] = new DataboxValues(entityListId: $field->entityListId);
        } elseif (FieldType::Tag === $field->type && null !== $workspaceId) {
            $constraints[] = new DataboxValues(workspaceId: $workspaceId);
        }

        return $constraints;
    }

    /**
     * @return Constraint[]
     */
    private static function itemConstraints(FieldDefinition $field): array
    {
        $constraints = match ($field->type) {
            FieldType::Text, FieldType::Textarea => [new Assert\Type('string')],
            FieldType::Number => [new Assert\Type('numeric')],
            FieldType::Date => [new Assert\Date()],
            FieldType::DateTime => [new Assert\Type('string'), new Assert\Regex(self::DATE_TIME_PATTERN, message: 'This value is not a valid datetime.')],
            FieldType::Boolean => [new Assert\Type('bool')],
            FieldType::Color => [new Assert\CssColor([Assert\CssColor::HEX_LONG, Assert\CssColor::HEX_SHORT])],
            FieldType::Choice => [new Assert\Choice(choices: $field->choices)],
            FieldType::Entity, FieldType::Tag => [new Assert\Uuid(strict: false)],
            null => [],
        };

        if (null !== $field->minLength || null !== $field->maxLength) {
            $constraints[] = new Assert\Length(min: $field->minLength, max: $field->maxLength);
        }
        if (null !== $field->min || null !== $field->max) {
            $constraints[] = new Assert\Range(min: $field->min, max: $field->max);
        }

        return $constraints;
    }
}
