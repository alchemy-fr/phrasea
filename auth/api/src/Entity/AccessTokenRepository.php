<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class AccessTokenRepository extends EntityRepository
{
    public function revokeTokens(User $user): void
    {
        foreach ([
                     AccessToken::class,
                     RefreshToken::class,
                     AuthCode::class,
                 ] as $class) {
            $this
                ->_em
                ->createQueryBuilder()
                ->delete()
                ->from($class, 't')
                ->andWhere('t.user = :user')
                ->setParameters([
                    'user' => $user->getId(),
                ])
                ->getQuery()
                ->execute();
        }
    }
}
