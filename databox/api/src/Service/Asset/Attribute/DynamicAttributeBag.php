<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Service\Asset\Attribute\Index\AttributeIndex;

class DynamicAttributeBag
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
    ) {
        $this->resolve = $resolve;
        $this->locales = array_unique([$locale, AttributeInterface::NO_LOCALE]);
    }

    public function __call(string $name, $args): ?string
    {
        $def = $this->definitions[$name] ?? null;
        if (null === $def) {
            return null;
        }

        if ($def->isMultiple()) {
            return null;
        }

        $defId = $def->getId();

        foreach ($this->locales as $l) {
            if (null !== $attr = $this->attributes->getAttribute($defId, $l)) {
                return $attr->getValue();
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
