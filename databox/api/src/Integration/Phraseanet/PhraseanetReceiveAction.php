<?php

declare(strict_types=1);

namespace App\Integration\Phraseanet;

use Alchemy\Workflow\Executor\RunContext;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;

final class PhraseanetReceiveAction extends AbstractIntegrationAction implements IfActionInterface
{
    final public const string JOB_ID = 'receive';

    public function handle(RunContext $context): void
    {
        if ($context->getInputs()['built'] ?? null) {
            return;
        }

        $context->retainJob();
    }
}
