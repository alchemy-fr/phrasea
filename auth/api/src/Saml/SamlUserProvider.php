<?php

declare(strict_types=1);

namespace App\Saml;

use App\Entity\SamlIdentity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SamlUserProvider implements UserProviderInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function loadUserByUsername($username)
    {
        $user = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin(SamlIdentity::class, 'i', Join::WITH, 'i.user = u.id')
            ->andWhere('i.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if ($user instanceof User) {
            return $user;
        }

        throw new UsernameNotFoundException('User not found');
    }

    public function refreshUser(UserInterface $user)
    {
    }

    public function supportsClass($class)
    {
        return $class === User::class;
    }
}
