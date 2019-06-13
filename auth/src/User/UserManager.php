<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
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

    /**
     * @var bool
     */
    private $validateEmail;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordEncoderInterface $passwordEncoder,
        bool $validateEmail
    )
    {
        $this->em = $em;
        $this->passwordEncoder = $passwordEncoder;
        $this->validateEmail = $validateEmail;
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

    private function generateToken(int $length):string
    {
        $length = ($length < 4) ? 4 : $length;
        return bin2hex(random_bytes(($length-($length%2))/2));
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

    public function removeUser(User $user): void
    {
        $this->em->remove($user);
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

    public function confirmEmail(string $userId, string $token): void
    {
        $user = $this->em->find(User::class, $userId);
        if (null === $user) {
            throw new BadRequestHttpException('User not found');
        }

        if (null === $user->getSecurityToken()
        || $user->getSecurityToken() !== $token) {
            throw new BadRequestHttpException('Invalid confirmation token');
        }

        $user->setEnabled(true);
        $user->setSecurityToken(null);
        $this->persistUser($user);
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
