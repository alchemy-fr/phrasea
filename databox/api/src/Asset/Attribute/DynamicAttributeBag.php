<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;

class DynamicAttributeBag
{
    private $resolve;

    /**
     * @param array<string, Attribute>           $attributes
     * @param array<string, AttributeDefinition> $definitions
     */
    public function __construct(
        private readonly array $attributes,
        private readonly array $definitions,
        callable $resolve,
        private readonly string $locale
    ) {
        $this->resolve = $resolve;
    }

    public function __call(string $name, $args): ?string
    {
        $def = $this->definitions[$name] ?? null;
        if (null === $def) {
            return null;
        }

        $defId = $def->getId();

        foreach ([$this->locale, IndexMappingUpdater::NO_LOCALE] as $l) {
            if (isset($this->attributes[$defId][$l])) {
                return $this->attributes[$defId][$l]->getValue();
            }
        }

        $resolve = $this->resolve;
        $attr = $resolve($def);

        if ($attr instanceof Attribute) {
            return $attr->getValue();
        }

        return null;
    }
}
