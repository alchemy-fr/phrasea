<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Automation;

class TriggerAutomationInput
{
    /**
     * Only count the matching and new results: no action is run and the cursor is left untouched.
     */
    public bool $dryRun = false;
}
