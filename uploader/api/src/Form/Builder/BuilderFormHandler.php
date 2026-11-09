<?php

declare(strict_types=1);

namespace App\Form\Builder;

use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class BuilderFormHandler
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * Only the fields of the form are kept: a user cannot write an attribute the form does not offer.
     * Hidden fields always get their default value.
     */
    public function prepare(FormDefinition $definition, array $data): array
    {
        $prepared = [];
        foreach ($definition->getInputFields() as $field) {
            $key = (string) $field->key;
            if ($field->hidden) {
                $value = $field->defaultValue;
            } elseif (array_key_exists($key, $data)) {
                $value = $data[$key];
            } else {
                continue;
            }

            $value = self::normalize($field, $value);
            if (!FieldDefinition::isEmpty($value)) {
                $prepared[$key] = $value;
            }
        }

        return $prepared;
    }

    /**
     * @return array<string, string[]> the messages by field key
     */
    public function validate(FormDefinition $definition, array $data): array
    {
        $data = $this->prepare($definition, $data);

        $errors = [];
        foreach ($definition->getInputFields() as $field) {
            $key = (string) $field->key;
            $violations = $this->validator->validate(
                $data[$key] ?? null,
                FieldConstraintFactory::create($field, $definition->workspaceId),
            );
            foreach ($violations as $violation) {
                $errors[$key][] = (string) $violation->getMessage();
            }
        }

        return $errors;
    }

    private static function normalize(FieldDefinition $field, mixed $value): mixed
    {
        if ($field->multiple) {
            if (FieldDefinition::isEmpty($value)) {
                return null;
            }
            $values = is_array($value) ? array_values($value) : [$value];
            $values = array_map(static fn (mixed $v): mixed => self::normalizeItem($field, $v), $values);

            return array_values(array_filter($values, static fn (mixed $v): bool => !FieldDefinition::isEmpty($v)));
        }

        return self::normalizeItem($field, $value);
    }

    private static function normalizeItem(FieldDefinition $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return match ($field->type) {
            FieldType::Number => is_string($value) && is_numeric($value) ? $value + 0 : $value,
            FieldType::Boolean => match ($value) {
                'true', '1', 1 => true,
                'false', '0', 0, '' => false,
                default => $value,
            },
            default => $value,
        };
    }
}
