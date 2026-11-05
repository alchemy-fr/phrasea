<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\Workflow\WorkflowOrchestrator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Workflow\WorkflowState;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CancelWorkflowProcessor implements ProcessorInterface
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private AuditLogManager $auditLogManager,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param WorkflowState $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WorkflowState
    {
        $this->workflowOrchestrator->cancelWorkflow($data->getId());

        $this->auditLogManager->log(AuditLogActionEnum::WorkflowCancelled, $data->getAsset(), [
            'workflowId' => $data->getId(),
        ]);
        $this->em->flush();

        return $data;
    }
}
