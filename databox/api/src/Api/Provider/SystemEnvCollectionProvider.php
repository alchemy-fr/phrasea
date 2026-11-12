<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\SecretScopeTrait;
use App\Integration\Env\EnvResolver;
use App\Model\SystemEnv;
use Doctrine\ORM\EntityManagerInterface;

final class SystemEnvCollectionProvider implements ProviderInterface
{
    use SecurityAwareTrait;
    use SecretScopeTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<SystemEnv>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // The scope only checks the access: the system variables are the same for every scope
        $this->getSecretScopeWorkspace($operation);

        return array_map(
            static fn (string $name): SystemEnv => new SystemEnv($name),
            EnvResolver::getSystemEnvNames(),
        );
    }
}
