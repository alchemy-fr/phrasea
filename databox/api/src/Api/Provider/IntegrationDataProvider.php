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

final class IntegrationDataProvider implements ProviderInterface
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $integrationId = (string) $uriVariables['integrationId'];
        $integration = $this->em->find(WorkspaceIntegration::class, $integrationId)
            ?? throw new NotFoundHttpException(sprintf('WorkspaceIntegration %s not found', $integrationId));

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $integration->getWorkspace());

        if (!$this->security->isGranted(AbstractVoter::EDIT, $integration)) {
            // Only the user's own data: the "userId" filter parameter applies this value whatever the query string says
            $userIdParameter = $operation->getParameters()?->get('userId') ?? throw new \LogicException('Missing "userId" parameter on the integration data collection');
            $userIdParameter->setValue($this->getStrictUserOrOAuthClient()->getUserIdentifier());
        }

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
