<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Automation;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

/**
 * Marks every current result of the automation as seen, without running any action.
 */
#[MessengerMessage('task')]
final readonly class ResetAutomationCursor
{
    public function __construct(
        public string $automationId,
    ) {
    }
}
