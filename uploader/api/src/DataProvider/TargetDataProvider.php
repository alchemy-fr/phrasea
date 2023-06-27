<?php

declare(strict_types=1);

namespace App\DataProvider;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Target;
use App\Security\Voter\TargetVoter;
use Symfony\Component\Security\Core\Security;

class TargetDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Target::class === $resourceClass && 'get' === $operationName;
    }

    public function __construct(private readonly CollectionDataProviderInterface $inner, private readonly Security $security)
    {
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $list = $this->inner->getCollection($resourceClass, $operationName);

        $items = [];
        /* @var Target $item */
        foreach ($list as $target) {
            if ($this->security->isGranted(TargetVoter::READ, $target)) {
                $items[] = $target;
            }
        }

        return $items;
    }
}
