<?php

declare(strict_types=1);

namespace App\Service\Discussion;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Discussion\Thread;
use App\Repository\Discussion\MessageRepository;
use App\Repository\Discussion\ThreadRepository;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final readonly class DiscussionManager
{
    public function __construct(
        private ThreadRepository $threadRepository,
        private MessageRepository $messageRepository,
        private ObjectMapper $objectMapper,
        private EntityManagerInterface $em,
    ) {
    }

    public function getThreadObject(Thread $thread): AbstractUuidEntity
    {
        $key = $thread->getKey();
        if (!str_contains((string) $key, ':')) {
            throw new \RuntimeException(sprintf('Invalid Thread key "%s"', $key));
        }
        [$objectKey, $objectId] = explode(':', (string) $key, 2);

        $object = $this->findObject($objectKey, $objectId);
        if (null === $object) {
            throw new \RuntimeException(sprintf('Object of Thread "%s" with key "%s" not found', $thread->getId(), $key));
        }

        return $object;
    }

    /**
     * Resolves the object a thread is about, whether or not the thread exists.
     */
    public function findObject(string $objectKey, string $objectId): ?AbstractUuidEntity
    {
        if (!Uuid::isValid($objectId)) {
            return null;
        }

        try {
            $className = $this->objectMapper->getClassName($objectKey);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $object = $this->em->find($className, $objectId);

        return $object instanceof AbstractUuidEntity ? $object : null;
    }

    public function getThreadOfObject(AbstractUuidEntity $object): ?Thread
    {
        return $this->threadRepository->getThreadOfKey(
            $this->getObjectKey($object)
        );
    }

    public function getObjectKey(AbstractUuidEntity $object): string
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        return sprintf('%s:%s', $objectKey, $object->getId());
    }

    public function getThreadMessages(string $threadId): iterable
    {
        return $this->messageRepository->getThreadMessages($threadId);
    }
}
