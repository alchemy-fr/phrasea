<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Security\InstancePermission;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class InstanceCapabilitiesProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $capabilities = [];
        foreach (InstancePermission::all() as $permission) {
            $capabilities[InstancePermission::API_KEYS[$permission]] = $this->security->isGranted($permission);
        }

        return new JsonResponse($capabilities);
    }
}
