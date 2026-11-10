<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Automation;

use ApiPlatform\Metadata\ApiProperty;

class SavedSearchAutomationInput
{
    /**
     * The saved search ID or IRI (on creation only).
     */
    public ?string $savedSearch = null;

    public ?string $name = null;

    /**
     * Limits the results of the saved search to this workspace (ID or IRI), an empty string to remove it.
     * Required by the "share_export" action.
     */
    public ?string $workspaceId = null;

    public ?bool $enabled = null;

    /**
     * Cron expression (at most every 5 minutes), an empty string for an automation run on demand only.
     */
    public ?string $schedule = null;

    /**
     * Timezone of the schedule (e.g. "Europe/Paris"), UTC by default.
     */
    public ?string $timezone = null;

    /**
     * "new" (results not handled by a previous run) or "all".
     */
    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => ['new', 'all'],
    ])]
    public ?string $scope = null;

    /**
     * Maximum number of assets handed to the actions per run, 1000 by default.
     */
    public ?int $maxAssetsPerRun = null;

    /**
     * Ordered list of {type, config}. A secret left empty keeps its current value.
     *
     * @var array<int, array{type: string, config: array}>|null
     */
    public ?array $actions = null;
}
