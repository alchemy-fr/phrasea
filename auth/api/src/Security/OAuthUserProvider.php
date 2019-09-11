<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ExternalAccessToken;
use App\Entity\User;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var UserManager
     */
    private $userManager;

    public function __construct(EntityManagerInterface $em, UserManager $userManager)
    {
        $this->em = $em;
        $this->userManager = $userManager;
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $response->getEmail();

        $user = $this->findUserByEmail($response->getEmail());
        if (null === $user) {
            $user = $this->userManager->createUser();
            $user->setEmail($response->getEmail());
        }

        $user->setEnabled(true);
        $this->userManager->persistUser($user);

        $accessToken = new ExternalAccessToken();
        $accessToken->setProvider($response->getResourceOwner()->getName());
        $accessToken->setIdentifier((string) $response->getUsername());
        $accessToken->setUser($user);
        $accessToken->setAccessToken($response->getAccessToken());
        if (null !== $response->getRefreshToken()) {
            $accessToken->setRefreshToken($response->getRefreshToken());
        }
        if (null !== $response->getExpiresIn()) {
            $expiresAt = new \DateTime();
            $expiresAt->setTimestamp(time() + (int) $response->getExpiresIn());
            $accessToken->setExpiresAt($expiresAt);
        }
        $this->em->persist($accessToken);
        $this->em->flush();

        return $user;
    }

    private function findUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy([
                'email' => $email,
            ]);
    }
}
