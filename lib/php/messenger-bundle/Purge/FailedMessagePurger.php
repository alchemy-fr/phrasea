<?php

declare(strict_types=1);

namespace Alchemy\MessengerBundle\Purge;

use Alchemy\MessengerBundle\Entity\MessengerMessage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes the messages of the failure transport, retried or not, up to a given id: the bound is
 * read when the purge is proposed, so that messages failing in the meantime are kept.
 */
final readonly class FailedMessagePurger
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getMaxId(): ?int
    {
        $maxId = $this->em->createQuery(sprintf('SELECT MAX(m.id) FROM %s m', MessengerMessage::class))
            ->getSingleScalarResult();

        return null === $maxId ? null : (int) $maxId;
    }

    public function countUpTo(int $maxId): int
    {
        return (int) $this->em->createQuery(sprintf('SELECT COUNT(m.id) FROM %s m WHERE m.id <= :max', MessengerMessage::class))
            ->setParameter('max', $maxId)
            ->getSingleScalarResult();
    }

    /**
     * @return int the number of deleted messages
     */
    public function purgeUpTo(int $maxId): int
    {
        return (int) $this->em->createQuery(sprintf('DELETE FROM %s m WHERE m.id <= :max', MessengerMessage::class))
            ->setParameter('max', $maxId)
            ->execute();
    }
}
