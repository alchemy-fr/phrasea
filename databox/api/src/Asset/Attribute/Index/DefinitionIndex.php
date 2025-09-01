<?php

namespace App\Asset\Attribute\Index;

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
        $locale = $attribute->getNearestWorkspaceLocale();

        if ($this->definition->isMultiple()) {
            $this->locales[$locale] ??= [];
            $this->locales[$locale][] = $attribute;
        } else {
            $this->locales[$locale] = $attribute;
        }
    }

    public function getAttribute(string $locale): ?Attribute
    {
        if ($this->definition->isMultiple()) {
            throw new \LogicException(sprintf('Cannot get single attribute for multiple definition "%s"', $this->definition->getId()));
        }

        return $this->locales[$locale] ?? null;
    }

    /**
     * @return Attribute[]|null
     */
    public function getAttributes(string $locale): ?array
    {
        if (!$this->definition->isMultiple()) {
            throw new \LogicException(sprintf('Cannot get multiple attributes for single definition "%s"', $this->definition->getId()));
        }

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
