<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserManager implements UserProviderInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    public function __construct(EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->em = $em;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function createUser(): User
    {
        $user = new User();
        $user->setSalt(rtrim(str_replace('+', '.', base64_encode(random_bytes(32))), '='));

        return $user;
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this
            ->em
            ->getRepository(User::class)
            ->findOneBy([
                'email' => $email,
            ]);
    }

    public function isPasswordValid(User $user, string $plainPassword): bool
    {
        return $this->passwordEncoder->isPasswordValid($user, $plainPassword);
    }

    public function encodePassword(User $user): void
    {
        if (null === $user) {
            throw new InvalidArgumentException('Missing user\'s plain password');
        }

        $hashedPassword = $this->passwordEncoder->encodePassword($user, $user->getPlainPassword());
        $user->setPassword($hashedPassword);
    }

    public function persistUser(User $user): void
    {
        $user->setUpdatedAt(new DateTime());
        $this->em->persist($user);
        $this->em->flush();
    }

    public function loadUserByUsername($username)
    {
        $user = $this->findUserByEmail($username);

        if (null === $user) {
            throw new UsernameNotFoundException(sprintf('user "%s" not found', $username));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
        $this->em->refresh($user);
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
