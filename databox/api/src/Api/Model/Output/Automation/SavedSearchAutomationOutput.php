<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Automation;

use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\AbstractUuidOutput;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Api\Model\Output\UserOutput;
use App\Entity\Automation\SavedSearchAutomation;
use Symfony\Component\Serializer\Attribute\Groups;

class SavedSearchAutomationOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'object',
        'properties' => [
            'edit' => 'boolean',
            'delete' => 'boolean',
            'run' => 'boolean',
        ],
    ])]
    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    protected array $capabilities = [];

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?string $savedSearchId = null;

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?string $name = null;

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public bool $enabled = true;

    /**
     * Cron expression, null when run on demand only.
     */
    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?string $schedule = null;

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?string $timezone = null;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => ['new', 'all'],
    ])]
    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?string $scope = null;

    /**
     * Ordered list of {type, config}: the secrets are never output (null + "<field>Set").
     */
    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public array $actions = [];

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?\DateTimeImmutable $nextRunAt = null;

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?\DateTimeImmutable $lastRunAt = null;

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public ?string $lastRunStatus = null;

    #[Groups([SavedSearchAutomation::GROUP_LIST, SavedSearchAutomation::GROUP_READ])]
    public int $consecutiveFailures = 0;

    #[Groups([SavedSearchAutomation::GROUP_READ])]
    public ?UserOutput $createdBy = null;
}
