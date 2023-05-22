<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Model;

class NeedList extends \ArrayObject
{
    public function has(string $jobId): bool
    {
        foreach ($this as $id) {
            if ($id === $jobId) {
                return true;
            }
        }

        return false;
    }
}
