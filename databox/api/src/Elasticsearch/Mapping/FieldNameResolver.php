<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Entity\Core\AttributeDefinition;

class FieldNameResolver
{
    public function __construct(private readonly AttributeTypeRegistry $attributeTypeRegistry, private readonly FacetRegistry $facetRegistry)
    {
    }

    public function getFieldName(AttributeDefinition $definition): string
    {
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

        return sprintf('%s_%s_%s',
            $definition->getSlug(),
            str_replace('_', '-', $type::getName()),
            $definition->isMultiple() ? 'm' : 's'
        );
    }

    /**
     * @return array{field: string, type: AttributeTypeInterface}
     */
    public function getFieldFromName(string $name): array
    {
        $facet = $this->facetRegistry->getFacet($name);
        if (null !== $facet) {
            $type = $this->attributeTypeRegistry->getStrictType($facet->getType());
            $f = $facet->getFieldName();
        } else {
            $info = $this->extractField($name);
            $type = $info['type'];
            $f = sprintf('attributes._.%s', $info['field']);
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
                'field' => sprintf('%s_%s_%s', $matches[1], $matches[2], $matches[3]),
                'type' => $this->attributeTypeRegistry->getStrictType(str_replace('-', '_', $matches[2])),
                'multiple' => 'm' === $matches[3],
            ];
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse field "%s"', $fieldName));
    }
}
