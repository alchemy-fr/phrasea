<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Executor\Expression\ExpressionParser;
use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\JobExecutionContext;
use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractIntegrationAction implements IfActionInterface
{
    private IntegrationManager $integrationManager;
    protected EntityManagerInterface $em;
    private ExpressionParser $expressionParser;

    /**
     * @return array{integration: IntegrationInterface, workspaceIntegration: WorkspaceIntegration, integrationId: string, workspaceId: string}
     */
    protected function getIntegrationConfig(JobExecutionContext|JobContext $context): IntegrationConfig
    {
        $integration = $this->integrationManager->loadIntegration($context->getInputs()['integrationId']);

        return $this->integrationManager->getIntegrationConfiguration($integration);
    }

    protected function getAsset(JobExecutionContext|JobContext $context): Asset
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

    #[Required]
    public function setExpressionParser(ExpressionParser $expressionParser): void
    {
        $this->expressionParser = $expressionParser;
    }

    public function evaluateIf(JobExecutionContext $context): bool
    {
        $asset = $this->getAsset($context);
        if (!$this->shouldRun($asset)) {
            return false;
        }

        $config = $this->getIntegrationConfig($context);
        if (null !== $if = $config->getWorkspaceIntegration()->getIf()) {
            return $this->expressionParser->evaluateIf($if, $context, [
                'asset' => $asset,
            ]);
        }

        return true;
    }

    protected function shouldRun(Asset $asset): bool
    {
        return true;
    }
}
