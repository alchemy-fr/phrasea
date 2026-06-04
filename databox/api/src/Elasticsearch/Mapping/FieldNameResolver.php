<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Elasticsearch\BuiltInField\BuiltInAttributeRegistry;
use App\Entity\Core\AttributeDefinition;

final readonly class FieldNameResolver
{
    public function __construct(
        private AttributeTypeRegistry $attributeTypeRegistry,
        private BuiltInAttributeRegistry $builtInFieldRegistry,
    ) {
    }

    public function getFieldNameFromDefinition(AttributeDefinition $definition): string
    {
        return $this->getFieldName($definition->getSlug(), $definition->getType(), $definition->isMultiple());
    }

    public function getFieldName(string $slug, string $type, bool $isMultiple): string
    {
        $attributeType = $this->attributeTypeRegistry->getStrictType($type);

        return sprintf('%s_%s_%s',
            $slug,
            $this->normalizeTypeNameForField($attributeType::getName()),
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
        $enabled = true;
        $builtInField = $this->builtInFieldRegistry->getBuiltInField($name);
        if (null !== $builtInField) {
            $type = $this->attributeTypeRegistry->getStrictType($builtInField->getType());
            $f = $builtInField::getName();
            $enabled = $builtInField->isEnabled();
        } else {
            $info = $this->extractFieldFromAttributeKey($name);
            $type = $info['type'];
            $f = sprintf('%s._.%s', AttributeInterface::ATTRIBUTES_FIELD, $info['key']);
            if (null !== $subField = $type->getAggregationField()) {
                $f .= '.'.$subField;
            }
        }

        return [
            'field' => $f,
            'type' => $type,
            'enabled' => $enabled,
        ];
    }

    /**
     * @return array{name: string, key: string, type: AttributeTypeInterface, multiple: bool}
     */
    private function extractFieldFromAttributeKey(string $attributeKey): array
    {
        if (1 === preg_match('#^(.+)_([^_]+)_([sm])$#', $attributeKey, $matches)) {
            return [
                'name' => $matches[1],
                'key' => $attributeKey,
                'type' => $this->attributeTypeRegistry->getStrictType(str_replace('-', '_', $matches[2])),
                'multiple' => 'm' === $matches[3],
            ];
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse attribute key "%s"', $attributeKey));
    }
}
