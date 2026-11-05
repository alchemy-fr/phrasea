<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Lock\LockTrait;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\IntegrationToken;
use App\Integration\Auth\MissingIntegrationTokenException;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\Sync\AssetToSync;
use App\Integration\Phrasea\Expose\Sync\ExposeAsset;
use App\Integration\Phrasea\Expose\Sync\ExposeSubDefinition;
use App\Integration\PusherTrait;
use App\Model\MediaClip;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Service\Clip\ClipException;
use App\Service\Clip\ClipFamily;
use App\Service\Clip\ClipResolver;
use App\Service\Storage\RenditionManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockInterface;

final class ExposeSynchronizer
{
    use PusherTrait;
    use LockTrait;

    public function __construct(
        private readonly ExposeClient $exposeClient,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly RenditionManager $renditionManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function synchronize(IntegrationData $basketData): void
    {
        $this->executeWithLock(
            'sync:'.$basketData->getId(),
            30,
            'synchronize',
            fn (LockInterface $lock) => $this->doSynchronize($basketData, $lock),
            // Cutting a clipped video may take a while: the lock is also refreshed on each item
            ttl: 600,
        );
    }

    private function doSynchronize(IntegrationData $basketData, LockInterface $lock): void
    {
        $config = $this->integrationManager->getIntegrationConfiguration($basketData->getIntegration());
        $token = $this->integrationTokenRepository->getLastValidUserToken($config->getIntegrationId(), $basketData->getUserId());
        if (!$token) {
            throw new MissingIntegrationTokenException($basketData->getUserId(), ExposeIntegration::getDisplayName(), 'Your Expose access has expired: the basket synchronization was stopped. Please authenticate again from the Expose integration panel and run a synchronization.');
        }

        ['id' => $publicationId, 'ignoreRoi' => $ignoreRoi] = ExposeIntegration::readPublicationData($basketData);
        $data = $this->exposeClient->getPublication($config, $token, $publicationId);

        /** @var ExposeAsset[] $exposeAssets */
        $exposeAssets = [];
        foreach ($data['assets'] as $asset) {
            if (!empty($asset['clientAnnotations'])) {
                $annotations = json_decode((string) $asset['clientAnnotations'], true, 512, JSON_THROW_ON_ERROR);
                $basketAssetId = $annotations['basketAssetId'] ?? null;
                if (null !== $basketAssetId) {
                    $subDefinitions = array_map(function (array $subDef): ExposeSubDefinition {
                        $subDefAnnotations = json_decode($subDef['clientAnnotations'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

                        return new ExposeSubDefinition(
                            $subDef['id'],
                            $subDef['name'],
                            $subDefAnnotations['renditionId'] ?? '',
                            $subDefAnnotations['fileId'] ?? '',
                            $subDefAnnotations['clip'] ?? null,
                        );
                    }, $asset['subDefinitions']);

                    $exposeAssets[$basketAssetId] = new ExposeAsset(
                        $asset['id'],
                        $basketAssetId,
                        $annotations['fileId'] ?? '',
                        $asset['name'] ?? null,
                        $asset['description'] ?? null,
                        $asset['translations'] ?? [],
                        $subDefinitions,
                        $annotations['clip'] ?? null,
                    );
                }
            }
        }

        /** @var Basket $basket */
        $basket = $basketData->getObject();

        /** @var AssetToSync[] $toSync */
        $toSync = [];
        foreach ($basket->getAssets() as $basketAsset) {
            if (!$basketAsset->getAsset()->getSource()) {
                continue;
            }

            $basketAssetId = $basketAsset->getId();
            if (isset($exposeAssets[$basketAssetId])) {
                $toSync[] = new AssetToSync(
                    $basketAsset,
                    $exposeAssets[$basketAssetId],
                );
                unset($exposeAssets[$basketAssetId]);
            } else {
                $toSync[] = new AssetToSync($basketAsset);
            }
        }

        $total = count($toSync);
        $done = 0;
        $dataId = $basketData->getId();

        $progress = function (int $done) use ($basket, $total, $dataId): void {
            $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
                'id' => $dataId,
                'action' => 'sync-progress',
                'total' => $total,
                'done' => $done,
            ], direct: true);
        };

        foreach ($toSync as $assetToSync) {
            $lock->refresh();
            $progress($done++);
            $basketAsset = $assetToSync->basketAsset;
            $asset = $basketAsset->getAsset();
            $source = $asset->getSource();
            $fileId = $source->getId();
            $basketClip = ClipResolver::forBasketAsset($basketAsset, $ignoreRoi);
            $sourceClip = ClipFamily::restrict($basketClip, $source->getType());
            $clipHash = $sourceClip?->getHash();

            $assetProperties = $this->exposeClient->getAssetProperties($asset, [
                'clientAnnotations' => json_encode(self::fileAnnotations([
                    'basketAssetId' => $basketAsset->getId(),
                    'fileId' => $fileId,
                ], $clipHash), JSON_THROW_ON_ERROR),
            ]);

            if (null === $assetToSync->exposeAsset) {
                $exposeAssetId = $this->postAsset($config, $token, $publicationId, $basketAsset, $assetProperties, $sourceClip);
                if (null === $exposeAssetId) {
                    continue;
                }
            } else {
                $exposeAssetId = $assetToSync->exposeAsset->id;
                if ($assetToSync->exposeAsset->fileId !== $fileId || $assetToSync->exposeAsset->clipHash !== $clipHash) {
                    $this->exposeClient->deleteAsset($config, $token, $assetToSync->exposeAsset->id);

                    $exposeAssetId = $this->postAsset($config, $token, $publicationId, $basketAsset, $assetProperties, $sourceClip);
                    if (null === $exposeAssetId) {
                        continue;
                    }

                    $assetToSync = new AssetToSync(
                        $basketAsset,
                        new ExposeAsset(
                            $exposeAssetId,
                            $basketAsset->getId(),
                            $fileId,
                            $assetProperties['name'],
                            $assetProperties['description'],
                            $assetProperties['translations'],
                            [],
                            $clipHash,
                        )
                    );
                } elseif (
                    $assetProperties['name'] !== ($assetToSync->exposeAsset->name ?? null)
                    || $assetProperties['description'] !== ($assetToSync->exposeAsset->description ?? null)
                    || $assetProperties['translations'] !== ($assetToSync->exposeAsset->translations ?? null)
                ) {
                    $this->exposeClient->putAsset($config, $token, $exposeAssetId, $assetProperties);
                }
            }

            $exposeSubDefinitions = $assetToSync->exposeAsset?->subDefinitions ?? [];

            $exposeSubDefs = [];
            foreach ($exposeSubDefinitions as $subDef) {
                $exposeSubDefs[$subDef->subDefinitionName] = $subDef;
            }

            foreach ([
                'preview',
                'thumbnail',
            ] as $renditionName) {
                if (null !== $rendition = $this->renditionManager->getAssetRenditionUsedAs($renditionName, $asset->getId())) {
                    $renditionFile = $rendition->getFile();
                    if (!$renditionFile) {
                        continue;
                    }

                    // The image thumbnail of a video only gets the crop
                    $renditionClip = ClipFamily::restrict($basketClip, $renditionFile->getType());
                    $renditionClipHash = $renditionClip?->getHash();

                    $existingSubDef = $exposeSubDefs[$renditionName] ?? null;
                    unset($exposeSubDefs[$renditionName]);

                    /** @var ExposeSubDefinition|null $existingSubDef */
                    if (null !== $existingSubDef) {
                        if ($existingSubDef->fileId === $renditionFile->getId() && $existingSubDef->clipHash === $renditionClipHash) {
                            continue;
                        }

                        $this->exposeClient->deleteSubDefinition($config, $token, $existingSubDef->id);
                    }

                    try {
                        $this->exposeClient->postSubDefinition(
                            $config,
                            $token,
                            $exposeAssetId,
                            $renditionName,
                            $rendition,
                            [
                                'clientAnnotations' => json_encode(self::fileAnnotations([
                                    'renditionId' => $rendition->getId(),
                                    'fileId' => $renditionFile->getId(),
                                ], $renditionClipHash), JSON_THROW_ON_ERROR),
                            ],
                            $renditionClip,
                        );
                    } catch (ClipException $e) {
                        // Never publish the whole file in place of the clipped one
                        $this->logger->error('Expose synchronization: a basket item rendition could not be clipped, it is not published', [
                            'exception' => $e,
                            'basketAssetId' => $basketAsset->getId(),
                            'renditionId' => $rendition->getId(),
                        ]);
                    }
                }
            }

            foreach ($exposeSubDefs as $subDef) {
                $this->exposeClient->deleteSubDefinition($config, $token, $subDef->id);
            }
        }
        $progress($total);

        $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
            'id' => $dataId,
            'action' => 'sync-clean',
        ], direct: true);
        foreach ($exposeAssets as $exposeAsset) {
            $this->exposeClient->deleteAsset($config, $token, $exposeAsset->id);
        }

        $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
            'id' => $dataId,
            'action' => 'sync-complete',
        ], direct: true);
    }

    /**
     * The clip hash is only set on clipped files: the items published before the clips
     * existed are not uploaded again.
     */
    private static function fileAnnotations(array $annotations, ?string $clipHash): array
    {
        if (null !== $clipHash) {
            $annotations['clip'] = $clipHash;
        }

        return $annotations;
    }

    /**
     * @return string|null the Expose asset id, null when the clipped file could not be produced
     */
    private function postAsset(IntegrationConfig $config, IntegrationToken $token, string $publicationId, BasketAsset $basketAsset, array $assetProperties, ?MediaClip $clip): ?string
    {
        try {
            return $this->exposeClient->postAsset($config, $token, $publicationId, $basketAsset->getAsset(), $assetProperties, $clip);
        } catch (ClipException $e) {
            // Never publish the whole file in place of the clipped one
            $this->logger->error('Expose synchronization: a basket item could not be clipped, it is not published', [
                'exception' => $e,
                'basketAssetId' => $basketAsset->getId(),
            ]);

            return null;
        }
    }
}
