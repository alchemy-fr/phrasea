<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Api\Model\Input\InputLimits;
use App\Attribute\Hierarchy\HierarchyAttributeFiller;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AssetAttributeBatchUpdateInput
{
    /**
     * @var AttributeActionInput[]
     */
    #[Assert\Valid]
    #[Assert\Count(max: InputLimits::MAX_IDS)]
    public ?array $actions = null;

    /**
     * What to do when a value disagrees with the hierarchy of an entity attribute:
     * "reject" (default) answers the conflicts, "replace" lets the written values win.
     */
    #[Assert\Choice(choices: HierarchyAttributeFiller::CONFLICT_STRATEGIES)]
    public ?string $hierarchyConflict = null;

    /**
     * Assets the whole batch applies to, on top of the ones of each action.
     */
    protected function getBatchAssetIds(): ?array
    {
        return null;
    }

    /**
     * The assets of all the actions (and of the batch itself) are loaded in a single SQL query.
     */
    #[Assert\Callback]
    public function validateDistinctAssetCount(ExecutionContextInterface $context): void
    {
        $assetIndex = [];
        $add = static function (?array $ids) use (&$assetIndex): void {
            foreach ($ids ?? [] as $id) {
                if (is_string($id) || is_int($id)) {
                    $assetIndex[$id] = true;
                }
            }
        };

        $add($this->getBatchAssetIds());
        foreach ($this->actions ?? [] as $action) {
            if ($action instanceof AttributeActionInput) {
                $add($action->assets);
            }
        }

        if (count($assetIndex) > InputLimits::MAX_IDS) {
            $context->buildViolation('Too many distinct assets ({{ count }} > {{ limit }}).')
                ->setParameter('{{ count }}', (string) count($assetIndex))
                ->setParameter('{{ limit }}', (string) InputLimits::MAX_IDS)
                ->atPath('actions')
                ->addViolation();
        }
    }
}
