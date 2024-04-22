<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\RenditionClass;
use App\Security\Voter\AbstractVoter;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;

class RenditionClassCollectionProvider extends AbstractCollectionProvider
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

        return array_filter($classes, fn (RenditionClass $renditionClass): bool => $this->security->isGranted(AbstractVoter::READ, $renditionClass));
    }
}
