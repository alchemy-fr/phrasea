<?php

namespace App\Integration\Auth;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Repository\Integration\IntegrationTokenRepository;
use Symfony\Contracts\Service\Attribute\Required;

trait IntegrationTokenTrait
{
    use SecurityAwareTrait;

    protected IntegrationTokenRepository $integrationTokenRepository;

    #[Required]
    public function setIntegrationTokenRepository(IntegrationTokenRepository $integrationTokenRepository): void
    {
        $this->integrationTokenRepository = $integrationTokenRepository;
    }

    public function getIntegrationToken(WorkspaceIntegration $workspaceIntegration): ?IntegrationToken
    {
        $userOrOAuthClient = $this->getStrictUserOrOAuthClient();
        $tokens = $this->integrationTokenRepository->getValidUserTokens($workspaceIntegration->getId(), $userOrOAuthClient->getUserIdentifier());

        return $tokens[0] ?? null;
    }
}
