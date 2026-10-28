<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;

/**
 * A value of $definition disagrees with the ancestors its hierarchy expects from $sources.
 */
final readonly class HierarchyConflict
{
    /**
     * @param AttributeEntity[] $expected
     * @param Attribute[]       $sources
     */
    public function __construct(
        public AttributeDefinition $definition,
        public ?Attribute $current,
        public array $expected,
        public array $sources,
        public bool $resolvable = true,
    ) {
    }

    public function getMessage(): string
    {
        $expected = implode(', ', array_map(fn (AttributeEntity $e): string => (string) $e->getValue(), $this->expected));
        $sources = implode(', ', array_unique(array_map(fn (Attribute $s): string => sprintf('%s', $s->getDefinition()->getName()), $this->sources)));

        if (null === $this->current) {
            return sprintf('Attribute "%s" holds a single value but the hierarchy of %s expects several ones: %s', $this->definition->getName(), $sources, $expected);
        }

        return sprintf('Attribute "%s" holds "%s" but the hierarchy of %s expects "%s"', $this->definition->getName(), $this->getCurrentLabel(), $sources, $expected);
    }

    public function getCurrentLabel(): ?string
    {
        return $this->current?->getValue();
    }
}
