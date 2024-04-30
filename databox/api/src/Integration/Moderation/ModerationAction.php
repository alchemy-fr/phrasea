<?php

declare(strict_types=1);

namespace App\Integration\Moderation;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;

final readonly class ModerationAction implements ActionInterface
{
    public function handle(RunContext $context): void
    {
        $context->retainJob();
    }
}
