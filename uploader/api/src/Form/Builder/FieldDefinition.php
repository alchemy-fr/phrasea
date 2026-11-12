<?php

declare(strict_types=1);

namespace App\Form\Builder;

final readonly class FieldDefinition
{
    /**
     * A key Databox reads as an attribute slug, optionally suffixed by the locale of its value ("title:fr").
     */
    private const string KEY_PATTERN = '#^[A-Za-z0-9_.-]+(:[A-Za-z_-]{2,5})?$#';

    /**
     * @param string[]              $choices the values a "choice" field accepts
     * @param array<string, string> $label   by locale, "_" for every other one
     */
    public function __construct(
        public string $id,
        public FieldSource $source,
        public ?string $key = null,
        public ?FieldType $type = null,
        public bool $multiple = false,
        public bool $required = false,
        public bool $hidden = false,
        public mixed $defaultValue = null,
        public ?FieldWidget $widget = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public int|float|null $min = null,
        public int|float|null $max = null,
        public array $choices = [],
        public ?string $entityListId = null,
        public array $label = [],
    ) {
    }

    public function isInput(): bool
    {
        return FieldSource::Layout !== $this->source;
    }

    /**
     * The label in the given locale, the one for every locale ("_"), or any one.
     */
    public function getLabel(?string $locale): string
    {
        return $this->label[$locale ?? '_'] ?? $this->label['_'] ?? $this->label['en'] ?? (array_values($this->label)[0] ?? $this->key ?? '');
    }

    public static function fromArray(mixed $data, string $path): self
    {
        if (!is_array($data)) {
            throw new InvalidFormDefinitionException('A field must be an object', $path);
        }

        $id = $data['id'] ?? null;
        if (!is_string($id) || '' === $id) {
            throw new InvalidFormDefinitionException('A field must have an id', $path.'.id');
        }

        $source = FieldSource::tryFrom((string) ($data['source'] ?? ''));
        if (null === $source) {
            throw new InvalidFormDefinitionException(sprintf('Invalid field source "%s"', get_debug_type($data['source'] ?? null)), $path.'.source');
        }

        $label = self::localizedMap($data['label'] ?? [], $path.'.label');

        if (FieldSource::Layout === $source) {
            $kind = $data['kind'] ?? 'section';
            if (!in_array($kind, ['section', 'text'], true)) {
                throw new InvalidFormDefinitionException('A layout item must be a "section" or a "text"', $path.'.kind');
            }

            return new self(id: $id, source: $source, label: $label);
        }

        $key = $data['key'] ?? null;
        if (!is_string($key) || 1 !== preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidFormDefinitionException(sprintf('Invalid field key "%s"', is_string($key) ? $key : get_debug_type($key)), $path.'.key');
        }

        $type = FieldType::tryFrom((string) ($data['type'] ?? ''));
        $multiple = (bool) ($data['multiple'] ?? false);
        $entityListId = null;
        $choices = [];

        switch ($source) {
            case FieldSource::Builtin:
                $builtin = Builtin::tryFrom($key);
                if (null === $builtin) {
                    throw new InvalidFormDefinitionException(sprintf('Unknown built-in field "%s"', $key), $path.'.key');
                }
                $type = $builtin->getType();
                $multiple = $builtin->isMultiple();
                break;
            case FieldSource::Attribute:
                if (null === $type || FieldType::Choice === $type || FieldType::Tag === $type) {
                    throw new InvalidFormDefinitionException('Invalid attribute field type', $path.'.type');
                }
                if (!is_array($data['attribute'] ?? null)) {
                    throw new InvalidFormDefinitionException('An attribute field must describe its attribute', $path.'.attribute');
                }
                if (FieldType::Entity === $type) {
                    $entityListId = $data['attribute']['entityListId'] ?? null;
                    if (!is_string($entityListId) || '' === $entityListId) {
                        throw new InvalidFormDefinitionException('An entity field must have an entity list', $path.'.attribute.entityListId');
                    }
                }
                break;
            default:
                if (null !== Builtin::tryFrom($key)) {
                    throw new InvalidFormDefinitionException(sprintf('"%s" is a built-in field', $key), $path.'.key');
                }
                if (null === $type || FieldType::Entity === $type || FieldType::Tag === $type) {
                    throw new InvalidFormDefinitionException('Invalid custom field type', $path.'.type');
                }
                if (FieldType::Choice === $type) {
                    $choices = self::choiceValues($data['choices'] ?? null, $path.'.choices');
                }
        }

        if ($multiple && FieldType::Boolean === $type) {
            throw new InvalidFormDefinitionException('A boolean field cannot be multiple', $path.'.multiple');
        }

        $widget = null;
        if (null !== ($data['widget'] ?? null)) {
            $widget = FieldWidget::tryFrom((string) $data['widget']);
            if (null === $widget) {
                throw new InvalidFormDefinitionException(sprintf('Invalid widget "%s"', $data['widget']), $path.'.widget');
            }
        }

        $required = (bool) ($data['required'] ?? false);
        $hidden = (bool) ($data['hidden'] ?? false);
        $defaultValue = $data['defaultValue'] ?? null;
        if ($hidden && $required && self::isEmpty($defaultValue)) {
            throw new InvalidFormDefinitionException('A hidden required field must have a default value', $path.'.defaultValue');
        }

        $constraints = $data['constraints'] ?? [];
        if (!is_array($constraints)) {
            throw new InvalidFormDefinitionException('Invalid constraints', $path.'.constraints');
        }

        return new self(
            id: $id,
            source: $source,
            key: $key,
            type: $type,
            multiple: $multiple,
            required: $required,
            hidden: $hidden,
            defaultValue: $defaultValue,
            widget: $widget,
            minLength: self::intOrNull($constraints['minLength'] ?? null, $path.'.constraints.minLength'),
            maxLength: self::intOrNull($constraints['maxLength'] ?? null, $path.'.constraints.maxLength'),
            min: self::numberOrNull($constraints['min'] ?? null, $path.'.constraints.min'),
            max: self::numberOrNull($constraints['max'] ?? null, $path.'.constraints.max'),
            choices: $choices,
            entityListId: $entityListId,
            label: $label,
        );
    }

    public static function isEmpty(mixed $value): bool
    {
        return null === $value || '' === $value || [] === $value;
    }

    /**
     * @return array<string, string>
     */
    private static function localizedMap(mixed $map, string $path): array
    {
        if (!is_array($map)) {
            throw new InvalidFormDefinitionException('Expected translations by locale', $path);
        }
        foreach ($map as $locale => $text) {
            if (!is_string($locale) || !is_string($text)) {
                throw new InvalidFormDefinitionException('Expected translations by locale', $path);
            }
        }

        return $map;
    }

    /**
     * @return string[]
     */
    private static function choiceValues(mixed $choices, string $path): array
    {
        if (!is_array($choices) || [] === $choices) {
            throw new InvalidFormDefinitionException('A choice field must have choices', $path);
        }

        $values = [];
        foreach (array_values($choices) as $i => $choice) {
            $value = is_array($choice) ? ($choice['value'] ?? null) : null;
            if (!is_string($value) || '' === $value) {
                throw new InvalidFormDefinitionException('A choice must have a value', $path.'.'.$i.'.value');
            }
            if (in_array($value, $values, true)) {
                throw new InvalidFormDefinitionException(sprintf('Duplicate choice "%s"', $value), $path.'.'.$i.'.value');
            }
            $values[] = $value;
        }

        return $values;
    }

    private static function intOrNull(mixed $value, string $path): ?int
    {
        if (null === $value) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new InvalidFormDefinitionException('Expected a positive integer', $path);
        }

        return $value;
    }

    private static function numberOrNull(mixed $value, string $path): int|float|null
    {
        if (null === $value) {
            return null;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidFormDefinitionException('Expected a number', $path);
        }

        return $value;
    }
}
