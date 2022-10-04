<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;

class DynamicAttributeBag
{
    /**
     * @var array<string, Attribute>
     */
    private array $attributes;

    /**
     * @var array<string, AttributeDefinition>
     */
    private array $definitions;
    private $resolve;
    private string $locale;

    public function __construct(
        array $attributesIndex,
        array $definitionsIndex,
        callable $resolve,
        string $locale
    ) {
        $this->attributes = $attributesIndex;
        $this->definitions = $definitionsIndex;
        $this->resolve = $resolve;
        $this->locale = $locale;
    }

    /**
     * called by twig for {{ attr.name }}, with name refering to another attribute.
     *
     * @param $args
     *
     * @return string|null the (resolved) value of the <name> attribute
     */
    public function __call(string $name, $args): ?string
    {
        $def = $this->definitions[$name] ?? null;
        if (null === $def) {
            return null;
        }

        $defId = $def->getId();

        // if a "real" or "fallback-computed" attribute already exists, return its value
        foreach ([$this->locale, IndexMappingUpdater::NO_LOCALE] as $l) {
            if (isset($this->attributes[$defId][$l])) {
                return $this->attributes[$defId][$l]->getResolvedValue();
            }
        }

        // no value yet exists: call the resolver (wich recurse call resolveAttrFallback(...) )
        $resolve = $this->resolve;
        $attr = $resolve($def);

        if ($attr instanceof Attribute) {
            return $attr->getResolvedValue();
        }

        return null;
    }
}
