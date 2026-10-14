<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IntegrationTokenDataProvider implements ProviderInterface
{
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

        if ($integration->getWorkspace()) {
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $integration->getWorkspace());
        }

        // Tokens are personal: only list the ones of the current user
        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            return [];
        }

        return $this->em->getRepository(IntegrationToken::class)->findBy([
            'integration' => $integration->getId(),
            'userId' => $user->getId(),
        ], [
            'createdAt' => 'ASC',
        ]);
    }
}
