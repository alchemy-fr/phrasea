<?php

namespace App\Asset\Attribute\Index;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;

final class DefinitionIndex
{
    private array $locales;

    public function __construct(private readonly AttributeDefinition $definition)
    {
    }

    /**
     * @return array{string, Attribute|Attribute[]}
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    public function getDefinition(): AttributeDefinition
    {
        return $this->definition;
    }

    public function addAttribute(Attribute $attribute): void
    {
        $locale = $attribute->getLocale() ?? AttributeInterface::NO_LOCALE;

        if ($this->definition->isMultiple()) {
            $this->locales[$locale] ??= [];
            $this->locales[$locale][] = $attribute;
        } else {
            $this->locales[$locale] = $attribute;
        }
    }

    public function getAttribute(string $locale): ?Attribute
    {
        return $this->locales[$locale] ?? null;
    }

    /**
     * @return Attribute[]
     */
    public function getFlattenAttributes(): array
    {
        return array_merge(...array_values(array_map(fn (array|Attribute $value): array => $value instanceof Attribute ? [$value] : $value, $this->locales)));
    }
}
