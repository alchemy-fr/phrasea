<?php

namespace App\Service;

use App\Entity\Traits\ErrorDisableInterface;
use App\Notification\UserNotifyableException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ErrorDisableHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private int $maxErrorCount = 3,
    ) {
    }

    public function handleError(ErrorDisableInterface $entity, UserNotifyableException $exception): void
    {
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
    }
}
