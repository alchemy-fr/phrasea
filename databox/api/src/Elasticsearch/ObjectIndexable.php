<?php

namespace App\Elasticsearch;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Entity\Core\Attribute;

final class ObjectIndexable
{
    private ?array $suggestTypes = null;

    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    public function isObjectIndexable(object $object): bool
    {
        if ($object instanceof Attribute) {
            if (empty($object->getValue())) {
                return false;
            }

            $definition = $object->getDefinition();

            return $definition->isEnabled()
                && $definition->isSearchable()
                    && (!$definition->isTranslatable() || null === $object->getLocale() || AttributeInterface::NO_LOCALE === $object->getLocale())
                && in_array($definition->getType(), $this->getSuggestTypes(), true);
        }

        return true;
    }

    private function getSuggestTypes(): array
    {
        if (null === $this->suggestTypes) {
            $this->suggestTypes = array_map(
                fn (AttributeTypeInterface $type): string => $type::getName(),
                array_filter(
                    $this->attributeTypeRegistry->getTypes(),
                    fn (AttributeTypeInterface $type): bool => $type->supportsSuggest()
                )
            );
        }

        return $this->suggestTypes;
    }
}
