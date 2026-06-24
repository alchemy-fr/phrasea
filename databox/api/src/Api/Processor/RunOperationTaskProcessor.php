<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Model\Input\OperationTaskInput;
use App\Entity\Admin\OperationTask;
use App\OperationTask\OperationTaskManager;
use Doctrine\ORM\EntityManagerInterface;

class RunOperationTaskProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OperationTaskManager $operationTaskManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param OperationTaskInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): OperationTask
    {
        $user = $this->getStrictUser();
        $this->validator->validate($data, $operation->getValidationContext() ?? []);

        return $this->operationTaskManager->createTask(
            $user,
            $data->task,
            $data->payload ?? [],
        );
    }
}
