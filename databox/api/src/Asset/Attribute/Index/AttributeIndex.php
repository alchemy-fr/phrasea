<?php

namespace App\Asset\Attribute\Index;

use App\Entity\Core\Attribute;

final class AttributeIndex
{
    /**
     * @var DefinitionIndex[]
     */
    private array $definitions = [];

    public function addAttribute(Attribute $attribute): void
    {
        $definition = $attribute->getDefinition();
        $definitionId = $definition->getId();
        $this->definitions[$definitionId] ??= new DefinitionIndex($definition);
        $this->definitions[$definitionId]->addAttribute($attribute);
    }

    public function removeDefinition(string $definitionId): void
    {
        unset($this->definitions[$definitionId]);
    }

    public function getAttribute(string $definitionId, string $locale): ?Attribute
    {
        return $this->definitions[$definitionId]?->getAttribute($locale);
    }

    /**
     * @return DefinitionIndex[]
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return Attribute[]
     */
    public function getFlattenAttributes(): array
    {
        $arrays = array_values(array_map(fn (DefinitionIndex $definitionIndex): array => $definitionIndex->getFlattenAttributes(), $this->definitions));

        return array_merge(...$arrays);
    }
}
