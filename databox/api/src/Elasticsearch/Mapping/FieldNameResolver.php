<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\FacetHandler;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class FieldNameResolver
{
    private AttributeTypeRegistry $attributeTypeRegistry;
    private EntityManagerInterface $em;
    private FacetRegistry $facetRegistry;

    public function __construct(
        AttributeTypeRegistry $attributeTypeRegistry,
        EntityManagerInterface $em,
        FacetRegistry $facetRegistry
    )
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
        $this->em = $em;
        $this->facetRegistry = $facetRegistry;
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
        $facet = $this->facetRegistry->getFacet($name);
        if (null !== $facet) {
            $type = $this->attributeTypeRegistry->getStrictType($facet->getType());
            $f = $facet->getFieldName();
        } else {
            $info = $this->extractField($name);
            $type = $this->attributeTypeRegistry->getStrictType($info['type']);
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
