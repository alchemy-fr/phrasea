<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Admin\OperationTask;
use App\Entity\Core\EntityList;
use App\OperationTask\Task\ImportEntitiesTask;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The state of an import queued by ImportEntitiesProcessor: {status, output, progress, itemTotal}, output holding the
 * error of a failed import. The task itself stays reserved to the admins.
 */
final class EntityListImportTaskProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        $taskId = (string) $uriVariables['taskId'];
        $task = Uuid::isValid($taskId) ? $this->em->find(OperationTask::class, $taskId) : null;
        if (null === $task || ImportEntitiesTask::NAME !== $task->getTask() || ($task->getPayload()['listId'] ?? null) !== $list->getId()) {
            throw new NotFoundHttpException('Import task not found');
        }

        return new JsonResponse([
            'status' => $task->getStatus(),
            'output' => $task->getOutput(),
            'progress' => $task->getProgress(),
            'itemTotal' => $task->getItemTotal(),
        ]);
    }
}
