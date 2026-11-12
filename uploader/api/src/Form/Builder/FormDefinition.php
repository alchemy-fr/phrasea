<?php

declare(strict_types=1);

namespace App\Form\Builder;

/**
 * A form designed with the Uploader form builder: what `Target::$form` holds.
 */
final readonly class FormDefinition
{
    public const int VERSION = 1;

    /**
     * @param FieldDefinition[] $fields
     */
    public function __construct(
        public ?string $workspaceId,
        public array $fields,
    ) {
    }

    /**
     * @throws InvalidFormDefinitionException
     */
    public static function fromArray(array $data): self
    {
        if (self::VERSION !== ($data['version'] ?? null)) {
            throw new InvalidFormDefinitionException(sprintf('Unsupported form definition version, expected %d', self::VERSION), 'version');
        }

        $workspaceId = $data['workspaceId'] ?? null;
        if (null !== $workspaceId && (!is_string($workspaceId) || '' === $workspaceId)) {
            throw new InvalidFormDefinitionException('Invalid workspace', 'workspaceId');
        }

        if (!is_array($data['fields'] ?? null) || !array_is_list($data['fields'])) {
            throw new InvalidFormDefinitionException('Expected a list of fields', 'fields');
        }

        $fields = [];
        $ids = [];
        $keys = [];
        foreach ($data['fields'] as $i => $item) {
            $path = 'fields.'.$i;
            $field = FieldDefinition::fromArray($item, $path);

            if (isset($ids[$field->id])) {
                throw new InvalidFormDefinitionException(sprintf('Duplicate field id "%s"', $field->id), $path.'.id');
            }
            $ids[$field->id] = true;

            if (null !== $field->key) {
                if (isset($keys[$field->key])) {
                    throw new InvalidFormDefinitionException(sprintf('Duplicate field key "%s"', $field->key), $path.'.key');
                }
                $keys[$field->key] = true;
            }

            if (null === $workspaceId && (FieldSource::Attribute === $field->source || FieldType::Tag === $field->type)) {
                throw new InvalidFormDefinitionException('Databox fields need the form to have a workspace', 'workspaceId');
            }

            $fields[] = $field;
        }

        return new self($workspaceId, $fields);
    }

    /**
     * @return FieldDefinition[]
     */
    public function getInputFields(): array
    {
        return array_values(array_filter($this->fields, static fn (FieldDefinition $f): bool => $f->isInput()));
    }

    public function getField(string $key): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }
}
