<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Entity\Integration\WorkspaceIntegration;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IntegrationTokenDataProvider implements ProviderInterface
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $integrationId = $uriVariables['integrationId'];
        $integration = $this->em->find(WorkspaceIntegration::class, $integrationId)
            ?? throw new NotFoundHttpException(sprintf('WorkspaceIntegration %s not found', $integrationId));

        if ($integration->getWorkspace()) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $integration->getWorkspace());
        }

        $filters = $context['filters'] ?? [];
        $filters['integrationId'] = $integrationId;
        $context['filters'] = $filters;

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
