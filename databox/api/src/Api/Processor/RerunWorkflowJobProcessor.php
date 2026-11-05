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

final readonly class RerunWorkflowJobProcessor implements ProcessorInterface
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
        $this->workflowOrchestrator->rerunJobs($data->getId(), $context['request']->attributes->get('jobId'));

        $this->auditLogManager->log(AuditLogActionEnum::WorkflowJobRerun, $data->getAsset(), [
            'workflowId' => $data->getId(),
            'jobId' => $context['request']->attributes->get('jobId'),
        ]);
        $this->em->flush();

        return $data;
    }
}
