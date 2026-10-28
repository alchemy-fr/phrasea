<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * What a structural change of an entity (move, removal) would affect.
 */
final class EntityHierarchyImpactOutput
{
    #[ApiProperty(identifier: true)]
    public string $id;

    /**
     * The entity and its descendants.
     */
    public int $entityCount = 0;

    /**
     * Attribute values referencing the entity or a descendant, in the definitions of its list.
     */
    public int $attributeCount = 0;

    /**
     * Of which entered by a user.
     */
    public int $humanAttributeCount = 0;

    /**
     * Definitions bound to the list hierarchy: {id, name, depth, autoFillParents}.
     *
     * @var list<array{id: string, name: ?string, depth: ?int, autoFillParents: bool}>
     */
    public array $definitions = [];
}
