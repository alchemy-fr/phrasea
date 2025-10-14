<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\BuiltInField\BuiltInFieldRegistry;
use App\Entity\Core\AttributeDefinition;

final readonly class FieldNameResolver
{
    public function __construct(
        private AttributeTypeRegistry $attributeTypeRegistry,
        private BuiltInFieldRegistry $builtInFieldRegistry,
    ) {
    }

    public function getFieldNameFromDefinition(AttributeDefinition $definition): string
    {
        return $this->getFieldName($definition->getSlug(), $definition->getFieldType(), $definition->isMultiple());
    }

    public function getFieldName(string $slug, string $fieldType, bool $isMultiple): string
    {
        $type = $this->attributeTypeRegistry->getStrictType($fieldType);

        return sprintf('%s_%s_%s',
            $slug,
            $this->normalizeTypeNameForField($type::getName()),
            $isMultiple ? 'm' : 's'
        );
    }

    public function normalizeTypeNameForField(string $type): string
    {
        return str_replace('_', '-', $type);
    }

    /**
     * @return array{field: string, type: AttributeTypeInterface}
     */
    public function getFieldFromName(string $name): array
    {
        if ('title' === $name) {
            return [
                'field' => $name,
                'type' => $this->attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
            ];
        }

        $builtInField = $this->builtInFieldRegistry->getBuiltInField($name);
        if (null !== $builtInField) {
            $type = $this->attributeTypeRegistry->getStrictType($builtInField->getType());
            $f = $builtInField->getFieldName();
        } else {
            $info = $this->extractField($name);
            $type = $info['type'];
            $f = sprintf('%s._.%s', AttributeInterface::ATTRIBUTES_FIELD, $info['field']);
            if (null !== $subField = $type->getAggregationField()) {
                $f .= '.'.$subField;
            }
        }

        return [
            'field' => $f,
            'type' => $type,
        ];
    }

    /**
     * @return array{name: string, field: string, type: AttributeTypeInterface, multiple: bool}
     */
    private function extractField(string $fieldName): array
    {
        if (1 === preg_match('#^(.+)_([^_]+)_([sm])$#', $fieldName, $matches)) {
            return [
                'name' => $matches[1],
                'field' => $fieldName,
                'type' => $this->attributeTypeRegistry->getStrictType(str_replace('-', '_', $matches[2])),
                'multiple' => 'm' === $matches[3],
            ];
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse field "%s"', $fieldName));
    }
}
