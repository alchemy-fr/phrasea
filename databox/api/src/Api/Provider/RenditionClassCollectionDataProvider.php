<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\RenditionClass;
use App\Security\Voter\RenditionClassVoter;
use App\Util\SecurityAwareTrait;

class RenditionClassCollectionDataProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): array|object {
        $criteria = [];
        $filters = $context['filters'] ?? [];

        if (isset($filters['workspaceId'])) {
            $criteria['workspace'] = $filters['workspaceId'];
        }

        $classes = $this->em->getRepository(RenditionClass::class)->findBy($criteria);

        return array_filter($classes, fn (RenditionClass $renditionClass): bool => $this->security->isGranted(RenditionClassVoter::READ, $renditionClass));
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return RenditionClass::class === $resourceClass;
    }
}
