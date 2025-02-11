<?php

namespace App\Service;

use App\Entity\Traits\ErrorDisableInterface;
use App\Notification\UserNotifyableException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class ErrorDisableHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $managerRegistry,
        private int $maxErrorCount = 3,
    ) {
    }

    public function handleError(ErrorDisableInterface $entity, UserNotifyableException $exception): void
    {
        $wasClosed = !$this->em->isOpen();
        if ($wasClosed) {
            $this->managerRegistry->resetManager();
            $entity = $this->em->find($entity::class, $entity->getId());
        }

        $entity->appendError([
            'date' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if ($entity->getErrorCount() >= $this->maxErrorCount) {
            $entity->disableAfterErrors();
        }

        $this->em->persist($entity);
        $this->em->flush();

        if ($wasClosed) {
            $this->em->close();
        }
    }
}
