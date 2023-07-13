<?php

declare(strict_types=1);

namespace App\DataProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Target;
use App\Security\Voter\TargetVoter;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class TargetDataProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $inner,
        private Security $security
    ) {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Target::class === $resourceClass && 'get' === $operationName;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $items = $this->inner->provide($operation, $uriVariables, $context);

        return array_values(array_filter($items, fn (Target $target): bool => $this->security->isGranted(TargetVoter::READ, $target)));
    }
}
