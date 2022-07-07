<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ExternalAccessToken;
use App\Entity\Group;
use App\Entity\User;
use App\OAuth\ResponsePathExtractor;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\PathUserResponse;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use InvalidArgumentException;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    private EntityManagerInterface $em;
    private UserManager $userManager;

    public function __construct(EntityManagerInterface $em, UserManager $userManager)
    {
        $this->em = $em;
        $this->userManager = $userManager;
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        if (empty($response->getEmail())) {
            throw new InvalidArgumentException(sprintf('User must have an email. Please check your "paths" mapping is correct!'));
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

    private function assignGroups(User $user, UserResponseInterface $response): void
    {
        if (!$response instanceof PathUserResponse) {
            return;
        }

        if (!isset($response->getPaths()['groups'])) {
            return;
        }
        $groups = ResponsePathExtractor::getValueForPath($response->getPaths(), $response->getData(), 'groups');
        if (null !== $groups) {
            $groups = array_map(function (string $name): string {
                return preg_replace('#^/#', '', $name);
            }, $groups);

            // remove old groups
            foreach ($user->getGroups() as $group) {
                if (!in_array($group, $groups, true)) {
                    $user->removeGroup($group);
                }
            }

            foreach ($groups as $groupName) {
                $user->addGroup($this->getGroupByName($groupName));
            }
        }
    }

    private function getGroupByName(string $name): Group
    {
        $group = $this->em->getRepository(Group::class)
            ->findOneBy([
                'name' => $name,
            ]);
        if ($group instanceof Group) {
            return $group;
        }

        $group = new Group();
        $group->setName($name);

        $this->em->persist($group);

        return $group;
    }

    private function findUserByUsername(string $username): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy([
                'username' => $username,
            ]);
    }
}
