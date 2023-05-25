<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Executor\JobContext;

interface IfActionInterface
{
    public function shouldRun(JobContext $context): bool;
}
