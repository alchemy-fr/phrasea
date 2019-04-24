<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserManager implements UserProviderInterface
{
    /**
     * @var EncoderFactoryInterface
     */
    private $encoderFactory;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em, EncoderFactoryInterface $encoderFactory)
    {
        $this->em = $em;
        $this->encoderFactory = $encoderFactory;
    }

    public function login(string $email, string $password)
    {
        $user = $this->findUserByEmail($email);

        if (!$user instanceof UserInterface) {
            return false;
        }

        $encoder = $this->encoderFactory->getEncoder($user);

        return $encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt());
    }

    private function findUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(
                [
                    'email' => $email,
                ]
            );
    }

    public function updateUser(UserInterface $user)
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        return $this->findUserByEmail($username);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        $this->em->refresh($user);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
