<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Automation;

use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\AbstractUuidOutput;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\UserOutput;
use App\Entity\Automation\AutomationRun;
use Symfony\Component\Serializer\Attribute\Groups;

class AutomationRunOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?string $automationId = null;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => ['manual', 'schedule'],
    ])]
    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?string $trigger = null;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public bool $dryRun = false;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?UserOutput $initiator = null;

    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => ['pending', 'running', 'success', 'partial', 'failed', 'skipped', 'cancelled'],
    ])]
    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?string $status = null;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?\DateTimeImmutable $startedAt = null;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?\DateTimeImmutable $endedAt = null;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public int $matchedCount = 0;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public int $newCount = 0;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public int $processedCount = 0;

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public int $failedCount = 0;

    /**
     * Per action: {type, status, processed, failed, skipped, errors: [{assetId?, message}]}.
     */
    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public array $actionResults = [];

    #[Groups([AutomationRun::GROUP_LIST, AutomationRun::GROUP_READ])]
    public ?string $error = null;
}
