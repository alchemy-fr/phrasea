<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Attribute\Hierarchy\HierarchyAttributeFiller;
use Symfony\Component\Validator\Constraints as Assert;

class AssetAttributeBatchUpdateInput
{
    /**
     * @var AttributeActionInput[]
     */
    #[Assert\Valid]
    public ?array $actions = null;

    /**
     * What to do when a value disagrees with the hierarchy of an entity attribute:
     * "reject" (default) answers the conflicts, "replace" lets the written values win.
     */
    #[Assert\Choice(choices: HierarchyAttributeFiller::CONFLICT_STRATEGIES)]
    public ?string $hierarchyConflict = null;
}
