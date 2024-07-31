<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeLocaleInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;

final readonly class IndexMappingTemplatesMaker
{
    public function __construct(
        private AttributeTypeRegistry $attributeTypeRegistry,
        private FieldNameResolver $fieldNameResolver,
    ) {
    }

    public function getAssetDynamicTemplates(): array
    {
        $templates = [];
        $locales = array_merge([AttributeInterface::NO_LOCALE], array_keys(AttributeLocaleInterface::LOCALES));

        foreach ($this->attributeTypeRegistry->getTypes() as $type) {
            $field = $this->fieldNameResolver->normalizeTypeNameForField($type::getName());

            if ($type->isMappingLocaleAware()) {
                foreach ($locales as $locale) {
                    $mapping = $this->getTypeMapping($type, $locale);
                    if (null !== $mapping) {
                        $templates[] = [
                            't_'.$type::getName().'_'.$locale => [
                                'match_pattern' => 'regex',
                                'path_match' => sprintf('^%s\.%s\..+_%s_[sm]$',
                                    AttributeInterface::ATTRIBUTES_FIELD,
                                    $locale,
                                    $field
                                ),
                                'mapping' => $mapping,
                            ],
                        ];
                    }
                }
            } else {
                $mapping = $this->getTypeMapping($type, AttributeInterface::NO_LOCALE);
                if (null !== $mapping) {
                    $templates[] = [
                        't_'.$type::getName() => [
                            'match_pattern' => 'regex',
                            'path_match' => sprintf('^%s\.[^.]+\..+_%s_[sm]$',
                                AttributeInterface::ATTRIBUTES_FIELD,
                                $field
                            ),
                            'mapping' => $mapping,
                        ],
                    ];
                }
            }
        }

        return $templates;
    }

    private function getTypeMapping(AttributeTypeInterface $type, string $locale): ?array
    {
        $typeMapping = $type->getElasticSearchMapping($locale);
        if (null === $typeMapping) {
            return null;
        }

        $mapping = array_merge([
            'type' => $type->getElasticSearchType(),
        ], $typeMapping);

        if (in_array($mapping['type'], [
            'object',
            'nested',
        ], true)) {
            unset($mapping['meta']);
        }

        return $mapping;
    }
}
