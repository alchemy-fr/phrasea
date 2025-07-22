<?php

declare(strict_types=1);

namespace App\Integration;

use Alchemy\Workflow\Executor\Expression\ExpressionParser;
use Alchemy\Workflow\Executor\JobContext;
use Alchemy\Workflow\Executor\JobExecutionContext;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Entity\Integration\WorkspaceIntegration;
use App\Notification\EntityDisableNotifyableException;
use App\Notification\ExceptionNotifier;
use App\Notification\UserNotifyableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractIntegrationAction implements IfActionInterface
{
    private IntegrationManager $integrationManager;
    protected EntityManagerInterface $em;
    private ExpressionParser $expressionParser;
    private ExceptionNotifier $exceptionNotifier;

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

    #[Required]
    public function setExceptionNotifier(ExceptionNotifier $exceptionNotifier): void
    {
        $this->exceptionNotifier = $exceptionNotifier;
    }

    public function evaluateIf(JobExecutionContext $context): bool
    {
        try {
            $asset = $this->getAsset($context);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

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

    protected function handleException(\Throwable $e, RunContext $context): void
    {
        if ($e instanceof UserNotifyableException
            || $e instanceof \InvalidArgumentException
            || $e instanceof RateLimitExceededException
        ) {
            $workspaceIntegration = $this->getIntegrationConfig($context)->getWorkspaceIntegration();
            $exception = new EntityDisableNotifyableException(
                $workspaceIntegration,
                sprintf('Integration "%s" error', $workspaceIntegration->getTitle() ?? $workspaceIntegration->getIntegration()),
                $e->getMessage(),
                previous: $e
            );
            if ($e instanceof UserNotifyableException) {
                $exception->addSubscribers($e->getSubscribers());
            }

            $this->exceptionNotifier->notifyException($exception);
        }

        throw $e;
    }

    final public function handle(RunContext $context): void
    {
        try {
            $this->doHandle($context);
        } catch (\Throwable $e) {
            $this->handleException($e, $context);
        }
    }

    abstract protected function doHandle(RunContext $context): void;

    protected function shouldRun(Asset $asset): bool
    {
        return true;
    }
}
