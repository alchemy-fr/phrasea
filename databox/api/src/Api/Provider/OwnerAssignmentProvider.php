<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Listener\OwnerPersistableInterface;
use App\Security\OwnerAssigner;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Assigns the current user as owner of a deserialized OwnerPersistableInterface body.
 *
 * Sits between the deserialization (and its securityPostDenormalize check, priority 300)
 * and the validation (priority 200) of the main provider chain, so that the owner is
 * known when the entity gets validated.
 */
#[AsDecorator(decorates: 'api_platform.state_provider.main', priority: 250)]
final readonly class OwnerAssignmentProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $decorated,
        private OwnerAssigner $ownerAssigner,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if ($data instanceof OwnerPersistableInterface && $operation->canDeserialize()) {
            $this->ownerAssigner->assignIfMissing($data);
        }

        return $data;
    }
}
