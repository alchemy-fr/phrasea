<?php

declare(strict_types=1);

namespace App\Integration\Moderation;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;

final class ModerationAction implements ActionInterface
{
    public function __construct()
    {
    }

    public function handle(RunContext $context): void
    {
        throw new \Exception('Refused ^^');
    }
}
