<?php

namespace App\Consumer\Handler\Basket;

use App\Entity\Integration\IntegrationBasketData;
use App\Integration\BasketActionsIntegrationInterface;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BasketUpdateHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
    )
    {
    }

    public function __invoke(BasketUpdate $message): void
    {
        $basketData = $this->em->getRepository(IntegrationBasketData::class)
            ->findBy([
                'object' => $message->getId(),
            ], [
                'integration' => 'ASC',
            ]);

        foreach ($basketData as $d) {
            $workspaceIntegration = $d->getIntegration();
            if (!$workspaceIntegration->isEnabled()) {
                continue;
            }

            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $integration = $config->getIntegration();
            if (!$integration instanceof BasketActionsIntegrationInterface) {
                continue;
            }

            $integration->handleBasketUpdate($d, $config);
        }
    }
}
