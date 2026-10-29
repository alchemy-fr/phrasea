<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * What a structural change of an entity (move, removal) would affect.
 */
final class EntityHierarchyImpactOutput
{
    public const string GROUP_READ = 'entity-hierarchy-impact:r';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_READ])]
    public string $id;

    /**
     * The entity and its descendants.
     */
    #[Groups([self::GROUP_READ])]
    public int $entityCount = 0;

    /**
     * Attribute values referencing the entity or a descendant, in the definitions of its list.
     */
    #[Groups([self::GROUP_READ])]
    public int $attributeCount = 0;

    /**
     * Of which entered by a user.
     */
    #[Groups([self::GROUP_READ])]
    public int $humanAttributeCount = 0;

    /**
     * Definitions bound to the list hierarchy: {id, name, depth, autoFillParents}.
     *
     * @var list<array{id: string, name: ?string, depth: ?int, autoFillParents: bool}>
     */
    #[Groups([self::GROUP_READ])]
    public array $definitions = [];
}
