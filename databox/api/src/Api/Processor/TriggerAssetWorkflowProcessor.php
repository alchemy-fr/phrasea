<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\Workflow\WorkflowOrchestrator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Util\SecurityAwareTrait;
use App\Workflow\Event\AssetIngestWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;

final class TriggerAssetWorkflowProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly WorkflowOrchestrator $workflowOrchestrator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($data->getId(), $data->getWorkspaceId()),
            [
                WorkflowState::INITIATOR_ID => $this->getStrictUser()->getId(),
            ]
        );

        return $data;
    }
}
