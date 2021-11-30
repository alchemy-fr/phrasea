<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AttributeDefinition;
use Cocur\Slugify\Slugify;

class FieldNameResolver
{
    private Slugify $slugify;
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(AttributeTypeRegistry $attributeTypeRegistry)
    {
        $this->slugify = new Slugify();
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    public function getFieldName(AttributeDefinition $definition): string
    {
        $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

        return sprintf('%s_%s_%s',
            $this->slugify->slugify($definition->getName()),
            $type->getElasticSearchType(),
            $definition->isMultiple() ? 'm' : 's'
        );
    }
}
