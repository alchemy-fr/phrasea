<?php

namespace App\Consumer\Handler\Basket;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Basket\Basket;
use App\Integration\BasketUpdateHandlerIntegrationInterface;
use App\Integration\IntegrationContext;
use App\Integration\IntegrationDataManager;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BasketUpdateHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegrationManager $integrationManager,
        private IntegrationDataManager $integrationDataManager,
    ) {
    }

    public function __invoke(BasketUpdate $message): void
    {
        // TODO cache whether there are basket integrations
        $workspaceIntegrations = $this->integrationManager->findIntegrationsOfContext(IntegrationContext::Basket);
        if (empty($workspaceIntegrations)) {
            return;
        }

        $basket = DoctrineUtil::findStrict($this->em, Basket::class, $message->getId());

        foreach ($workspaceIntegrations as $workspaceIntegration) {
            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $integration = $config->getIntegration();
            if (!$integration instanceof BasketUpdateHandlerIntegrationInterface) {
                continue;
            }

            $basketData = $this->integrationDataManager->findBy([
                'object' => $basket,
                'integration' => $workspaceIntegration,
            ]);

            foreach ($basketData as $d) {
                $integration->handleBasketUpdate($d, $config);
            }
        }
    }
}
