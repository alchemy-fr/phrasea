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
}
