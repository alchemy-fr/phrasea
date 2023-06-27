<?php

declare(strict_types=1);

namespace App\Entity;

use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Entity\AuthCode;
use Alchemy\OAuthServerBundle\Entity\RefreshToken;
use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function deleteUserCascade(User $user): void
    {
        try {
            $this->_em->beginTransaction();

            foreach ([
                         AuthCode::class,
                         AccessToken::class,
                         RefreshToken::class,
                         ResetPasswordRequest::class,
                         ExternalAccessToken::class,
                     ] as $entity) {
                $this
                    ->_em
                    ->createQueryBuilder()
                    ->delete()
                    ->from($entity, 'a')
                    ->andWhere('a.user = :user')
                    ->setParameter('user', $user->getId())
                    ->getQuery()
                    ->execute();
            }

            $this->_em->remove($user);
            $this->_em->flush();
            $this->_em->commit();
        } catch (\Throwable $e) {
            $this->_em->rollback();
            throw $e;
        }
    }
}
