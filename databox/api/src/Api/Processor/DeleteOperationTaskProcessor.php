<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Admin\OperationTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Deletes a task that has reached a final state: a pending or running one must be cancelled first.
 */
final readonly class DeleteOperationTaskProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param OperationTask $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data->isTerminated()) {
            throw new ConflictHttpException('Only a completed, failed or cancelled task can be deleted');
        }

        $this->em->remove($data);
        $this->em->flush();

        return null;
    }
}
