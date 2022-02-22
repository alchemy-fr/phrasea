<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\RenditionClass;
use App\Security\Voter\RenditionClassVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class RenditionClassCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $criteria = [];
        $filters = $context['filters'] ?? [];
        if (isset($filters['workspaceId'])) {
            $criteria['workspace'] = $filters['workspaceId'];
        }

        $classes = $this->em->getRepository(RenditionClass::class)->findBy($criteria);

        return array_filter($classes, function (RenditionClass $renditionClass): bool {
            return $this->security->isGranted(RenditionClassVoter::READ, $renditionClass);
        });
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return RenditionClass::class === $resourceClass;
    }

}
