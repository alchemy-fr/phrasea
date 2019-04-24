<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserManager
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
        $this->em->persist($user);
        $this->em->flush();
    }
}
