<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Entity;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Entity\Core\AttributeEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttributeEntityPendingNotifyHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotifierManager $notifierManager,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(AttributeEntityPendingNotify $message): void
    {
        $entity = $this->em->find(AttributeEntity::class, $message->getId());
        if (null === $entity || AttributeEntity::STATUS_PENDING !== $entity->getStatus()) {
            return;
        }

        $list = $entity->getList();
        $ownerId = $list?->getOwnerId();
        if (null === $ownerId) {
            return;
        }

        $authorId = $entity->getCreatorId();
        if ($ownerId === $authorId) {
            return;
        }

        $author = 'Deleted User';
        if (null !== $authorId) {
            $user = $this->userRepository->getUser($authorId);
            $author = $user ? $user['username'] : 'Deleted User';
        }

        $workspaceId = $list->getWorkspace()->getId();

        $this->notifierManager->notifyUser($ownerId, 'entity_list:pending_value', [
            'value' => $entity->getValue(),
            'listName' => $list->getName() ?? $list->getId(),
            'listId' => $list->getId(),
            'workspaceId' => $workspaceId,
            'url' => '/workspaces/'.$workspaceId.'/manage/entities',
            'author' => $author,
            'authorId' => $authorId,
        ]);
    }
}
