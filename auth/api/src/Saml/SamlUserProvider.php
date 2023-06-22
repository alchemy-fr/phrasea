<?php

declare(strict_types=1);

namespace App\Saml;

use App\Entity\SamlIdentity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SamlUserProvider implements UserProviderInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function loadUserByUsername($username, string $idpName = null)
    {
        $user = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin(SamlIdentity::class, 'i', Join::WITH, 'i.user = u.id')
            ->andWhere('u.username = :username')
            ->setParameter('username', $username)
            ->andWhere('i.provider = :provider')
            ->setParameter('provider', $idpName)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if ($user instanceof User) {
            return $user;
        }

        throw new UserNotFoundException('User not found');
    }

    /**
     * @param User $user
     */
    public function refreshUser(UserInterface $user)
    {
        return $this->em->find(User::class, $user->getId());
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->loadUserByUsername($identifier);
    }
}
