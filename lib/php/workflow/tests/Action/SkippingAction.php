<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Action;

use Alchemy\Workflow\Exception\JobSkipExceptionInterface;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;

final class SkippingAction implements ActionInterface
{
    public const string IF_REASON = 'Nothing to do';
    public const string EXCEPTION_REASON = 'Gave up while running';

    public function evaluateIf(JobExecutionContext $context): bool
    {
        $context->setSkipReason(self::IF_REASON);

        return false;
    }

    public function handle(RunContext $context): void
    {
        throw new class(self::EXCEPTION_REASON) extends \RuntimeException implements JobSkipExceptionInterface {
            public function shouldSkipJob(): bool
            {
                return true;
            }
        };
    }
}
