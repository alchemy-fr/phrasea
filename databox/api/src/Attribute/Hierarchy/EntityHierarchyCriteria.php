<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

/**
 * Hierarchy filters of the entity listing (parent, depth, branch, placeholder, definition),
 * applied by AttributeEntityHierarchyExtension (ORM) and AttributeEntitySearch (Elasticsearch).
 */
final class EntityHierarchyCriteria
{
    public function __construct(
        public ?string $parentId = null,
        public ?int $depth = null,
        public ?string $branchId = null,
        public ?bool $placeholder = null,
        public ?string $listId = null,
        /** Depth range, bounds included (from a definition) */
        public ?int $minDepth = null,
        public ?int $maxDepth = null,
        /** The definition matches nothing (bound to a list without hierarchy...) */
        public bool $none = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->parentId
            && null === $this->depth
            && null === $this->branchId
            && null === $this->placeholder
            && null === $this->listId
            && null === $this->minDepth
            && null === $this->maxDepth
            && !$this->none;
    }
}
