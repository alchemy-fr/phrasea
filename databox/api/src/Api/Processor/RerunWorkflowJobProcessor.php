<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\Workflow\WorkflowOrchestrator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Workflow\WorkflowState;

final readonly class RerunWorkflowJobProcessor implements ProcessorInterface
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
    ) {
    }

    /**
     * @param WorkflowState $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WorkflowState
    {
        $this->workflowOrchestrator->rerunJobs($data->getId(), $context['request']->attributes->get('jobId'));

        return $data;
    }
}
