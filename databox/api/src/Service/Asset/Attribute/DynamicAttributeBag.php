<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Service\Asset\Attribute\Index\AttributeIndex;

final class DynamicAttributeBag
{
    private $resolve;
    private readonly array $locales;

    /**
     * @param array<string, AttributeDefinition> $definitions
     */
    public function __construct(
        private readonly AttributeIndex $attributes,
        private readonly array $definitions,
        callable $resolve,
        string $locale,
        private readonly array $parentDefinitions,
    ) {
        $this->resolve = $resolve;
        $this->locales = array_unique([$locale, AttributeInterface::NO_LOCALE]);
    }

    public function __call(string $name, $args): string|array|null
    {
        $def = $this->definitions[$name] ?? null;
        if (null === $def) {
            return null;
        }

        $defId = $def->getId();
        if (in_array($defId, $this->parentDefinitions, true)) {
            throw new \RuntimeException(sprintf('Circular reference detected for attribute definition "%s"', $def->getSlug()));
        }

        $isMultiple = $def->isMultiple();

        if ($isMultiple) {
            $values = [];
            foreach ($this->locales as $l) {
                if (null !== $attrs = $this->attributes->getAttributes($defId, $l)) {
                    foreach ($attrs as $attr) {
                        $values[] = $attr->getValue();
                    }
                }
            }
            if (!empty($values)) {
                return $values;
            }
        } else {
            foreach ($this->locales as $l) {
                if (null !== $attr = $this->attributes->getAttribute($defId, $l)) {
                    return $attr->getValue();
                }
            }
        }

        $resolve = $this->resolve;
        $attributes = $resolve($def);

        if ($isMultiple) {
            return array_map(fn (Attribute $attr): ?string => $attr->getValue(), $attributes);
        } elseif ($attributes instanceof Attribute) {
            return $attributes->getValue();
        }

        return null;
    }
}
