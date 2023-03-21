<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

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
    public function mergeWith(self $list): self
    {
        /** @var Stage[]|self $new */
        $new = new self();
        $selfCount = $this->count();
        $listCount = $list->count();
        $maxCount = max($selfCount, $listCount);

        for ($i = 0; $i < $maxCount; $i++) {
            $stage = new Stage();
            $new->append($stage);
            $runList = $stage->getRuns();

            if ($i >= $selfCount) {
                $runList->mergeWith($list[$i]->getRuns());
            } elseif ($i >= $listCount) {
                $runList->mergeWith($this[$i]->getRuns());
            } else {
                $runList->mergeWith($this[$i]->getRuns());
                $runList->mergeWith($list[$i]->getRuns());
            }
        }

        return $new;
    }
}
