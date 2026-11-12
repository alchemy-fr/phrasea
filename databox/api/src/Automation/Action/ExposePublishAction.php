<?php

declare(strict_types=1);

namespace App\Automation\Action;

use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Entity\Core\Asset;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\ExposeAssetPublisher;
use App\Integration\Phrasea\Expose\ExposeClient;
use App\Integration\Phrasea\Expose\ExposeIntegration;
use App\Integration\Phrasea\Expose\Sync\ExposeAsset;
use App\Model\AutomationScopeEnum;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\WorkspaceIntegrationVoter;
use App\Service\Clip\ClipResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Publishes the assets to an Expose publication, with the Expose access of the owner of the saved search
 * (who must be an editor of the publication): the source file (cut to the region of interest, unless
 * ignored) and the renditions used as preview and thumbnail.
 *
 * Each published asset is annotated with the automation and the asset it comes from: a run only
 * sends what changed since the previous one, and only touches the assets published by its automation.
 * With "removeMissing" (scope "all" only), the assets of the automation which are no longer in the
 * results are removed from the publication at the end of the run, unless the run stopped at its limit
 * of assets (the results after the limit were not gone through).
 */
#[AsTaggedItem(index: self::TYPE)]
final class ExposePublishAction extends AbstractAutomationAction implements ScopeAwareActionInterface
{
    public const string TYPE = 'expose_publish';

    final public const string MISSING_ACCESS = 'Expose access missing for the automation owner: authorize the Expose integration again';
    final public const string RUN_TRUNCATED = 'The assets which left the results were not removed: the run stopped at its limit of assets';

    /** @var string array<string, ExposeAsset>, the assets of the automation in the publication, by asset ID */
    private const string STATE_INDEX = 'index';
    /** @var string the error which fails the remaining assets of the run */
    private const string STATE_ERROR = 'destinationError';
    /** @var string array<string, true>, every asset of the results seen during the run */
    private const string STATE_MATCHED = 'matched';
    /** @var string the number of assets failed by this action */
    private const string STATE_FAILED = 'failed';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly ExposeClient $exposeClient,
        private readonly ExposeAssetPublisher $publisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $integrationId = self::normalizeId(self::getRequiredString($config, 'integrationId'));
        $publicationId = self::normalizeId(self::getRequiredString($config, 'publicationId'));
        $ignoreRoi = self::getBool($config, 'ignoreRoi');
        $removeMissing = self::getBool($config, 'removeMissing');

        $integration = Uuid::isValid($integrationId) ? $this->em->find(WorkspaceIntegration::class, $integrationId) : null;
        if (!$integration instanceof WorkspaceIntegration || ExposeIntegration::NAME !== $integration->getIntegration()) {
            self::throwViolations(['integrationId' => 'Expose integration not found.']);
        }
        if (!$integration->isEnabled()) {
            self::throwViolations(['integrationId' => 'This integration is disabled.']);
        }
        if (!$this->isGranted(WorkspaceIntegrationVoter::INTERACT, $integration)) {
            self::throwViolations(['integrationId' => 'You are not allowed to use this integration.']);
        }

        $token = $this->integrationTokenRepository->getLastValidUserToken($integrationId, $this->getStrictUser()->getId());
        if (null === $token) {
            self::throwViolations(['integrationId' => 'Authorize the Expose integration first.']);
        }

        $integrationConfig = $this->integrationManager->getIntegrationConfiguration($integration);
        try {
            $publication = $this->exposeClient->getPublication($integrationConfig, $token, $publicationId);
        } catch (HttpExceptionInterface $e) {
            self::throwViolations(match ($e->getResponse()->getStatusCode()) {
                401 => ['integrationId' => 'Your Expose access was refused: authorize the Expose integration again.'],
                403, 404 => ['publicationId' => 'Publication not found.'],
                default => ['publicationId' => sprintf('Expose could not be reached: %s', $e->getMessage())],
            });
        } catch (\Throwable $e) {
            self::throwViolations(['publicationId' => sprintf('Expose could not be reached: %s', $e->getMessage())]);
        }
        if (!self::canEdit($publication)) {
            self::throwViolations(['publicationId' => 'You are not an editor of this publication.']);
        }

        return [
            'integrationId' => $integrationId,
            'publicationId' => $publicationId,
            'publicationTitle' => is_string($publication['title'] ?? null) ? $publication['title'] : null,
            'ignoreRoi' => $ignoreRoi,
            'removeMissing' => $removeMissing,
        ];
    }

    public function getScopeViolations(array $config, AutomationScopeEnum $scope): array
    {
        if (true === ($config['removeMissing'] ?? false) && AutomationScopeEnum::All !== $scope) {
            return ['removeMissing' => 'Removing the assets which left the results requires the scope "all".'];
        }

        return [];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $state = $batch->state;
        $config = $batch->config;
        $context = $batch->context;

        foreach ($batch->matchedAssetIds as $assetId) {
            $state->data[self::STATE_MATCHED][$assetId] = true;
        }

        $assets = array_values($batch->assets);
        if (empty($assets)) {
            return;
        }

        try {
            [$integrationConfig, $token] = $this->connect($state, $config, $context);
        } catch (\Throwable $e) {
            $this->failRemaining($state, $assets, $e->getMessage());

            return;
        }

        $automationAnnotations = ['automationId' => $context->automationId];
        foreach ($assets as $i => $asset) {
            // Publishing a large file may take a while
            $batch->keepAlive();
            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                $state->markSkipped($asset, 'Not allowed to read the asset');
                continue;
            }
            if (null === $asset->getSource()) {
                $state->markSkipped($asset, 'No file to publish');
                continue;
            }

            $assetId = $asset->getId();
            try {
                $published = $this->publisher->publish(
                    $integrationConfig,
                    $token,
                    $config['publicationId'],
                    $asset,
                    ClipResolver::forAsset($asset, $config['ignoreRoi'] ?? false),
                    $assetId,
                    $automationAnnotations + ['assetId' => $assetId],
                    $state->data[self::STATE_INDEX][$assetId] ?? null,
                );
            } catch (\Throwable $e) {
                if (null !== $destinationError = self::getDestinationError($e)) {
                    $state->data[self::STATE_ERROR] = $destinationError;
                    $this->failRemaining($state, array_slice($assets, $i), $destinationError);

                    return;
                }

                $this->logger->warning(sprintf('Automation "%s" could not publish asset "%s" to Expose: %s', $context->automationId, $assetId, $e->getMessage()), [
                    'exception' => $e,
                ]);
                $this->fail($state, $asset, $e->getMessage());
                continue;
            }

            if (null === $published) {
                // The previous version was removed before publishing the new one
                unset($state->data[self::STATE_INDEX][$assetId]);
                $state->markSkipped($asset, 'The region of interest could not be applied: nothing published');
                continue;
            }

            $state->data[self::STATE_INDEX][$assetId] = $published;
            $state->markProcessed($asset);
        }
    }

    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void
    {
        try {
            if (!($config['removeMissing'] ?? false) || AutomationScopeEnum::All !== $context->scope) {
                return;
            }
            // A failed asset is never removed: the assets this action did not see are only known when it failed them
            if (isset($state->data[self::STATE_ERROR]) || $state->getFailed() > ($state->data[self::STATE_FAILED] ?? 0)) {
                return;
            }
            if ($state->runTruncated) {
                $state->addNotice(self::RUN_TRUNCATED);

                return;
            }

            try {
                [$integrationConfig, $token] = $this->connect($state, $config, $context);
            } catch (\Throwable $e) {
                $state->setError(sprintf('The assets which left the results were not removed: %s', $e->getMessage()));

                return;
            }

            $matched = $state->data[self::STATE_MATCHED] ?? [];
            $errors = [];
            /** @var ExposeAsset $exposeAsset */
            foreach ($state->data[self::STATE_INDEX] ?? [] as $assetId => $exposeAsset) {
                if (isset($matched[$assetId])) {
                    continue;
                }

                try {
                    $this->exposeClient->deleteAsset($integrationConfig, $token, $exposeAsset->id);
                } catch (HttpExceptionInterface $e) {
                    if (404 !== $e->getResponse()->getStatusCode()) {
                        $errors[] = sprintf('%s: %s', $assetId, $e->getMessage());
                    }
                } catch (\Throwable $e) {
                    $errors[] = sprintf('%s: %s', $assetId, $e->getMessage());
                }
            }

            if (!empty($errors)) {
                $state->setError(sprintf('Some assets which left the results could not be removed from the publication (%s)', implode(', ', $errors)));
            }
        } finally {
            $state->data = [];
        }
    }

    /**
     * Loads the integration and the Expose access of the owner (both reloaded at each batch: the entity
     * manager is cleared in between), and reads the publication once per run.
     *
     * @return array{0: IntegrationConfig, 1: IntegrationToken}
     *
     * @throws \RuntimeException when the publication cannot be used: the remaining assets fail alike
     */
    private function connect(AutomationActionState $state, array $config, AutomationRunContext $context): array
    {
        if (isset($state->data[self::STATE_ERROR])) {
            throw new \RuntimeException($state->data[self::STATE_ERROR]);
        }

        try {
            $integration = $this->em->find(WorkspaceIntegration::class, $config['integrationId']);
            if (!$integration instanceof WorkspaceIntegration || !$integration->isEnabled()) {
                throw new \RuntimeException('The Expose integration of the action was deleted or disabled');
            }
            if (!$this->isGranted(WorkspaceIntegrationVoter::INTERACT, $integration)) {
                throw new \RuntimeException('The automation owner is not allowed to use the Expose integration');
            }

            $token = $this->integrationTokenRepository->getLastValidUserToken($integration->getId(), $context->ownerId)
                ?? throw new \RuntimeException(self::MISSING_ACCESS);
            $integrationConfig = $this->integrationManager->getIntegrationConfiguration($integration);

            if (!isset($state->data[self::STATE_INDEX])) {
                $state->data[self::STATE_INDEX] = $this->readPublication($integrationConfig, $token, $config['publicationId'], $context->automationId);
            }
        } catch (\Throwable $e) {
            $state->data[self::STATE_ERROR] = self::getDestinationError($e) ?? $e->getMessage();

            throw new \RuntimeException($state->data[self::STATE_ERROR], 0, $e);
        }

        return [$integrationConfig, $token];
    }

    /**
     * @return array<string, ExposeAsset> the assets published by the automation, by asset ID
     */
    private function readPublication(IntegrationConfig $integrationConfig, IntegrationToken $token, string $publicationId, string $automationId): array
    {
        try {
            $publication = $this->exposeClient->getPublication($integrationConfig, $token, $publicationId);
        } catch (HttpExceptionInterface $e) {
            if (in_array($e->getResponse()->getStatusCode(), [403, 404], true)) {
                throw new \RuntimeException(sprintf('The Expose publication "%s" was not found', $publicationId), 0, $e);
            }

            throw $e;
        }

        // Without the editor rights, the annotations are not returned: everything would be published again
        if (!self::canEdit($publication)) {
            throw new \RuntimeException(sprintf('The automation owner is not an editor of the Expose publication "%s"', $publication['title'] ?? $publicationId));
        }

        return $this->publisher->readPublicationAssets(
            $publication,
            fn (array $annotations): ?string => $automationId === ($annotations['automationId'] ?? null) && isset($annotations['assetId'])
                ? (string) $annotations['assetId']
                : null,
        );
    }

    /**
     * @param Asset[] $assets
     */
    private function failRemaining(AutomationActionState $state, array $assets, string $message): void
    {
        foreach ($assets as $asset) {
            $this->fail($state, $asset, $message);
        }
    }

    private function fail(AutomationActionState $state, Asset $asset, string $message): void
    {
        $state->data[self::STATE_FAILED] = ($state->data[self::STATE_FAILED] ?? 0) + 1;
        $state->markFailed($asset, $message);
    }

    private static function canEdit(array $publication): bool
    {
        return false !== ($publication['capabilities']['edit'] ?? null);
    }

    /**
     * @return string|null the error when the publication itself cannot be used
     */
    private static function getDestinationError(\Throwable $e): ?string
    {
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof HttpExceptionInterface) {
                return match ($cause->getResponse()->getStatusCode()) {
                    401 => self::MISSING_ACCESS,
                    403 => 'The automation owner is not allowed to publish to the Expose publication: they must be an editor of it',
                    default => null,
                };
            }
            if ($cause instanceof \InvalidArgumentException && 'Token was expired' === $cause->getMessage()) {
                return self::MISSING_ACCESS;
            }
        }

        return null;
    }

    private static function getBool(array $config, string $key): bool
    {
        $value = $config[$key] ?? false;
        if (!is_bool($value)) {
            self::throwViolations([$key => 'This value should be a boolean.']);
        }

        return $value;
    }
}
