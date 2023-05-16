<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractIntegrationAction implements ActionInterface
{
    private IntegrationManager $integrationManager;
    private EntityManagerInterface $em;

    protected function getIntegrationConfig(RunContext $context): array
    {
        $integration = $this->integrationManager->loadIntegration($context->getInputs()['integrationId']);

        return $this->integrationManager->getIntegrationConfiguration($integration);
    }

    protected function getAsset(JobContext $context): Asset
    {
        $assetId = $context->getInputs()['assetId'];
        $asset = $this->em->find(Asset::class, $assetId);

        if (!$asset instanceof Asset) {
            throw new \InvalidArgumentException(sprintf('Asset "%s" not found for %s', $assetId, static::class));
        }

        return $asset;
    }

    #[Required]
    public function setIntegrationManager(IntegrationManager $integrationManager): void
    {
        $this->integrationManager = $integrationManager;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
