<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Profile\Profile;
use App\Repository\Profile\ProfileRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The default display profile of the current user (see
 * ProfileRepository::findUserDefault()): none answers 404.
 */
final readonly class UserDefaultProfileProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ProfileRepository $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Profile
    {
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            return null;
        }

        return $this->repository->findUserDefault($user->getId(), $user->getGroups());
    }
}
