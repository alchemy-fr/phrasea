<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
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

    public function getFieldFromName(string $name): FieldInfoDto
    {
        $builtInField = $this->builtInFieldRegistry->getBuiltInField($name);
        if (null !== $builtInField) {
            return new FieldInfoDto(
                $builtInField::getName(),
                $this->attributeTypeRegistry->getStrictType($builtInField->getType()),
                $builtInField->isEnabled()
            );
        }
        $info = $this->extractFieldFromAttributeKey($name);

        return new FieldInfoDto(
            sprintf('%s._.%s', AttributeInterface::ATTRIBUTES_FIELD, $info->key),
            $info->type,
            true,
        );

    }

    private function extractFieldFromAttributeKey(string $attributeKey): ExtractedFieldDto
    {
        if (1 === preg_match('#^(.+)_([^_]+)_([sm])$#', $attributeKey, $matches)) {
            return new ExtractedFieldDto(
                $matches[1],
                $attributeKey,
                $this->attributeTypeRegistry->getStrictType(str_replace('-', '_', $matches[2])),
                'm' === $matches[3],
            );
        }

        throw new \InvalidArgumentException(sprintf('Cannot parse attribute key "%s"', $attributeKey));
    }
}
