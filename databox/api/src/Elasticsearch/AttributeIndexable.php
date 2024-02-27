<?php

namespace App\Elasticsearch;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\Attribute;

final readonly class AttributeIndexable
{
    public function __construct(
        private AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    public function isAttributeIndexable(Attribute $attribute): bool
    {
        if (strlen($attribute->getValue()) > 300) {
            return false;
        }

        $type = $this->attributeTypeRegistry->getStrictType($attribute->getDefinition()->getFieldType());

        return $type->supportsSuggest();
    }
}
