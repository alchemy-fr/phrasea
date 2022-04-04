<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Entity\Core\AttributeDefinition;
use InvalidArgumentException;

class FieldNameResolver
{
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(AttributeTypeRegistry $attributeTypeRegistry)
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    public function getFieldName(AttributeDefinition $definition): string
    {
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

        return sprintf('%s_%s_%s',
            $definition->getSlug(),
            $type->getElasticSearchType(),
            $definition->isMultiple() ? 'm' : 's'
        );
    }

    public function extractField(string $fieldName): array
    {
        $types = array_map(function (AttributeTypeInterface $t): string {
            return $t->getElasticSearchType();
        }, $this->attributeTypeRegistry->getTypes());

        $regex = sprintf('#^(.+)_(%s)_(s|m)$#', implode('|', $types));
        if (1 === preg_match($regex, $fieldName, $matches)) {
            return [
                'name' => $matches[1],
                'field' => sprintf('%s_%s_%s', $matches[1], $matches[2], $matches[3]),
                'type' => $matches[2],
                'multiple' => 'm' === $matches[3],
            ];
        }

        throw new InvalidArgumentException(sprintf('Cannot parse field "%s"', $fieldName));
    }
}
