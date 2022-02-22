<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\Workspace;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\RenditionClassVoter;
use App\Security\Voter\WorkspaceVoter;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

class AttributeDefinitionCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
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
        $filters = $context['filters'] ?? [];
        if (!isset($filters['workspaceId'])) {
            throw new InvalidArgumentException(sprintf('You must provide "workspaceId" to filter out attributes'));
        }

        $workspace = $this->em->find(Workspace::class, $filters['workspaceId']);
        if (!$workspace instanceof Workspace) {
            throw new InvalidArgumentException(sprintf('Workspace "%s" does not exist', $workspace));
        }

        if (!$this->security->isGranted(WorkspaceVoter::READ, $workspace)) {
            throw new AccessDeniedHttpException();
        }

        $criteria = [
            'workspace' => $workspace->getId(),
        ];

        return $this->em->getRepository(AttributeDefinition::class)->findBy($criteria);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AttributeDefinition::class === $resourceClass;
    }

}
