<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ExternalAccessToken;
use App\Entity\User;
use App\OAuth\GroupParser;
use App\User\GroupMapper;
use App\User\UserManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\PathUserResponse;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    const AUTH_ORIGIN = 'authOrigin';
    private EntityManagerInterface $em;
    private UserManager $userManager;
    private GroupMapper $groupMapper;
    private GroupParser $groupParser;
    private SessionInterface $session;

    public function __construct(
        EntityManagerInterface $em,
        UserManager $userManager,
        GroupMapper $groupMapper,
        GroupParser $groupParser,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->groupMapper = $groupMapper;
        $this->groupParser = $groupParser;
        $this->session = $requestStack->getSession();
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        if (empty($response->getEmail())) {
            throw new InvalidArgumentException('User must have an email. Please check your "paths" mapping is correct!');
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
            $expiresAt = new DateTime();
            $expiresAt->setTimestamp(time() + (int) $response->getExpiresIn());
            $accessToken->setExpiresAt($expiresAt);
        }
        $this->em->persist($accessToken);
        $this->em->flush();

        $this->session->set(self::AUTH_ORIGIN, $providerName);

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
