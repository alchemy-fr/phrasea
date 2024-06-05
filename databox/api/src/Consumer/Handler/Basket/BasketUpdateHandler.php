<?php

namespace App\Consumer\Handler\Basket;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Basket\Basket;
use App\Integration\BasketActionsIntegrationInterface;
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
    )
    {
    }

    public function __invoke(BasketUpdate $message): void
    {
        $basket = DoctrineUtil::findStrict($this->em, Basket::class, $message->getId());

        $basketData = $this->integrationDataManager->findBy([
                'object' => $basket,
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
