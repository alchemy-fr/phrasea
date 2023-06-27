<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserManager implements UserProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $passwordHasher, private readonly bool $validateEmail)
    {
    }

    public function createUser(): User
    {
        $user = new User();
        $user->setSalt(rtrim(str_replace('+', '.', base64_encode(random_bytes(32))), '='));
        $user->setEnabled(!$this->validateEmail);
        if ($this->validateEmail) {
            $user->setSecurityToken($this->generateToken(32));
        }

        return $user;
    }

    private function generateToken(int $length): string
    {
        $length = ($length < 4) ? 4 : $length;

        return bin2hex(random_bytes(($length - ($length % 2)) / 2));
    }

    public function findUserByUsername(string $username): ?User
    {
        return $this
            ->em
            ->getRepository(User::class)
            ->findOneBy([
                'username' => $username,
            ]);
    }

    public function isPasswordValid(User $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }

    public function encodePassword(User $user): void
    {
        if (null === $user) {
            throw new \InvalidArgumentException('Missing user\'s plain password');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
        $user->setPassword($hashedPassword);
    }

    public function persistUser(User $user): void
    {
        $user->setUpdatedAt(new \DateTime());
        $this->em->persist($user);
        $this->em->flush();
    }

    public function removeUser(User $user): void
    {
        $this->em->getRepository(User::class)->deleteUserCascade($user);
    }

    public function loadUserByUsername($username)
    {
        $user = $this->findUserByUsername($username);

        if (null === $user) {
            throw new UserNotFoundException(sprintf('user "%s" not found', $username));
        }

        return $user;
    }

    public function getUserByIdAndToken(string $userId, string $token): User
    {
        try {
            /** @var User $user */
            $user = $this->em->find(User::class, $userId);
        } catch (ConversionException $e) {
            throw new BadRequestHttpException('Invalid ID', $e);
        }
        if (null === $user) {
            throw new BadRequestHttpException('User not found');
        }

        if (null === $user->getSecurityToken()
            || $user->getSecurityToken() !== $token) {
            throw new BadRequestHttpException('Invalid confirmation token');
        }

        return $user;
    }

    public function confirmEmail(User $user): void
    {
        $user->setEnabled(true);
        $user->setEmailVerified(true);
        $user->setSecurityToken(null);
        $this->persistUser($user);
    }

    public function refreshUser(UserInterface $user)
    {
        $this->em->refresh($user);

        return $user;
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
