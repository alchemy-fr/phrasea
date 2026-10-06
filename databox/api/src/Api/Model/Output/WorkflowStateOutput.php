<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Workflow\WorkflowState;
use Symfony\Component\Serializer\Attribute\Groups;

class WorkflowStateOutput
{
    #[Groups(['_'])]
    #[ApiProperty(identifier: true)]
    public string $id;

    #[Groups([WorkflowState::GROUP_LIST])]
    public string $name;

    #[Groups([WorkflowState::GROUP_LIST])]
    public int $status;

    /**
     * Rank of the run among the runs of the same workflow on its asset.
     */
    #[Groups([WorkflowState::GROUP_LIST])]
    public ?int $number = null;

    #[Groups([WorkflowState::GROUP_LIST])]
    public ?string $eventName = null;

    #[Groups([WorkflowState::GROUP_LIST])]
    public \DateTimeImmutable $startedAt;

    #[Groups([WorkflowState::GROUP_LIST])]
    public ?\DateTimeImmutable $endedAt = null;

    #[Groups([WorkflowState::GROUP_LIST])]
    public ?string $duration = null;

    #[Groups([WorkflowState::GROUP_LIST])]
    public ?string $assetId = null;

    #[Groups([WorkflowState::GROUP_LIST])]
    public ?string $assetName = null;
}
