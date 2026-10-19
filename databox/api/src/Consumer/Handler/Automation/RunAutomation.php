<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Automation;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('task')]
final readonly class RunAutomation
{
    public function __construct(
        public string $runId,
    ) {
    }
}
