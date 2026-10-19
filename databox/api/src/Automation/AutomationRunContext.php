<?php

declare(strict_types=1);

namespace App\Automation;

use App\Model\AutomationTriggerEnum;

/**
 * What an action may know about the run it is part of.
 *
 * Scalars only: the entity manager is cleared between batches.
 */
final readonly class AutomationRunContext
{
    public function __construct(
        public string $runId,
        public string $automationId,
        public string $automationName,
        public string $savedSearchId,
        public ?string $savedSearchName,
        public string $ownerId,
        public AutomationTriggerEnum $trigger,
        public bool $dryRun,
    ) {
    }
}
