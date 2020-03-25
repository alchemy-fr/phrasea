<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Entity;

use Doctrine\ORM\EntityRepository;

class AccessTokenRepository extends EntityRepository
{
    public function revokeTokens(string $userId): void
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
                    'user' => $userId,
                ])
                ->getQuery()
                ->execute();
        }
    }
}
