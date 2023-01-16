<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\FacetHandler;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use InvalidArgumentException;

class FieldNameResolver
{
    private AttributeTypeRegistry $attributeTypeRegistry;
    private AttributeTypeRegistry $typeRegistry;

    public function __construct(AttributeTypeRegistry $attributeTypeRegistry, AttributeTypeRegistry $typeRegistry)
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
        $this->typeRegistry = $typeRegistry;
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

    public function getFieldFromName(string $name)
    {
        $type = TextAttributeType::getName();
        $isAttr = false;
        $property = null;

        $normalizer = function ($value) {
            return $value;
        };

        switch ($name) {
            case FacetHandler::FACET_WORKSPACE:
                $f = 'workspaceId';
                $property = 'workspace';
                $normalizer = function (Workspace $workspace): string {
                    return $workspace->getName();
                };
                break;
            case FacetHandler::FACET_COLLECTION:
                $f = 'collectionPaths';
                $property = 'collections';
                $normalizer = function (CollectionAsset $collectionAsset): string {
                    return $collectionAsset->getCollection()->getTitle();
                };
                break;
            case FacetHandler::FACET_TAG:
                $property = $f = 'tags';
                $normalizer = function (Tag $tag): string {
                    return $tag->getName();
                };
                break;
            case FacetHandler::FACET_PRIVACY:
                $property = $f = 'privacy';
                $normalizer = function (int $value): string {
                    return WorkspaceItemPrivacyInterface::LABELS[$value];
                };
                break;
            case FacetHandler::FACET_CREATED_AT:
                $type = DateTimeAttributeType::getName();
                $property = $f = 'createdAt';
                break;
            default:
                $isAttr = true;
                $info = $this->extractField($name);
                $t = $this->typeRegistry->getStrictType($info['type']);
                $type = $t::getName();
                $f = sprintf('attributes._.%s', $info['field']);
                if (null !== $subField = $t->getAggregationField()) {
                    $f .= '.'.$subField;
                }
                break;
        }

        return [
            'field' => $f,
            'property' => $property,
            'type' => $this->typeRegistry->getStrictType($type),
            'isAttr' => $isAttr,
            'normalizer' => $normalizer,
        ];
    }

    private function extractField(string $fieldName): array
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
