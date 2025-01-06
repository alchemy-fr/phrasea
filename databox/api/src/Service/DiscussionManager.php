<?php

namespace App\Service;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Discussion\Thread;
use App\Repository\Discussion\MessageRepository;
use App\Repository\Discussion\ThreadRepository;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DiscussionManager
{
    public function __construct(
        private ThreadRepository $threadRepository,
        private MessageRepository $messageRepository,
        private ObjectMapper $objectMapper,
        private EntityManagerInterface $em,
    )
    {
    }

    public function getThreadObject(Thread $thread): AbstractUuidEntity
    {
        $key = $thread->getKey();
        if (!str_contains($key, ':')) {
            throw new \RuntimeException(sprintf('Invalid Thread key "%s"', $key));
        }
        [$objectKey, $objectId] = explode(':', $key);
        $className = $this->objectMapper->getClassName($objectKey);

        $object = $this->em->find($className, $objectId);
        if (null === $object) {
            throw new \RuntimeException(sprintf('Object of Thread "%s" with key "%s" not found', $thread->getId(), $key));
        }

        return $object;
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
