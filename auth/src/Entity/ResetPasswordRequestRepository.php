<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class ResetPasswordRequestRepository extends EntityRepository
{
    public function findLastUserRequest(User $user): ?ResetPasswordRequest
    {
        /** @var ResetPasswordRequest|null $request */
        $request = $this->findOneBy([
            'user' => $user->getId(),
        ]);

        return $request;
    }

    public function revokeRequests(User $user): void
    {
        $this
            ->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.user = :user')
            ->setParameters([
                'user' => $user->getId(),
            ])
            ->getQuery()
            ->execute();
    }
}
