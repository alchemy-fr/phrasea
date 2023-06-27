<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ExternalAccessToken;
use App\Entity\User;
use App\OAuth\GroupParser;
use App\User\GroupMapper;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\PathUserResponse;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    public const AUTH_ORIGIN = 'authOrigin';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserManager $userManager,
        private readonly GroupMapper $groupMapper,
        private readonly GroupParser $groupParser,
        private readonly RequestStack $requestStack
    ) {
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        if (empty($response->getEmail())) {
            throw new \InvalidArgumentException('User must have an email. Please check your "paths" mapping is correct!');
        }

        $user = $this->findUserByUsername($response->getEmail());
        if (null === $user) {
            $user = $this->userManager->createUser();
            $user->setUsername($response->getEmail());
            $user->setEnabled(true);
        }

        $this->assignGroups($user, $response);

        $this->userManager->persistUser($user);

        $accessToken = new ExternalAccessToken();
        $providerName = $response->getResourceOwner()->getName();
        $accessToken->setProvider($providerName);
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

        $this->requestStack->getSession()->set(self::AUTH_ORIGIN, $providerName);

        return $user;
    }

    private function assignGroups(User $user, UserResponseInterface $response): void
    {
        $providerName = $response->getResourceOwner()->getName();
        if (!$response instanceof PathUserResponse) {
            return;
        }

        if (!isset($response->getPaths()['groups'])) {
            return;
        }

        $groups = $this->groupParser->extractGroups($response);

        if (null !== $groups) {
            $this->groupMapper->updateGroups($providerName, $user, $groups);
        }
    }

    private function findUserByUsername(string $username): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy([
                'username' => $username,
            ]);
    }
}
