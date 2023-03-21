<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Planner;

final class StageList extends \ArrayObject
{
    public function containsJobId(string $jobId): bool
    {
        /** @var Stage $stage */
        foreach ($this as $stage) {
            if ($stage->containsJobId($jobId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Stage[] $list
     */
    public function mergeWithCopy(self $list): self
    {
        /** @var Stage[]|self $new */
        $new = new self();
        $selfCount = $this->count();
        $listCount = $list->count();
        $maxCount = max($selfCount, $listCount);

        for ($i = 0; $i < $maxCount; $i++) {
            $stage = new Stage();
            $runs = $stage->getRuns();

            if ($i >= $selfCount) {
                $runs->mergeWith($list[$i]->getRuns());
            } elseif ($i >= $listCount) {
                $runs->mergeWith($this[$i]->getRuns());
            } else {
                $runs->mergeWith($this[$i]->getRuns());
                $runs->mergeWith($list[$i]->getRuns());
            }

            $new->append($stage);
        }

        return $new;
    }
}
