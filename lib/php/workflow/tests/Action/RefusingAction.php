<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Action;

use Alchemy\Workflow\Exception\JobRefusalExceptionInterface;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;

final class RefusingAction implements ActionInterface
{
    public const string REASON = 'The input cannot be processed';

    public function handle(RunContext $context): void
    {
        throw new class(self::REASON) extends \RuntimeException implements JobRefusalExceptionInterface {};
    }
}
