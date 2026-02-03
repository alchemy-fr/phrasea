<?php

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Lock\LockTrait;
use App\Entity\Basket\Basket;
use App\Entity\Integration\IntegrationData;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\Sync\AssetToSync;
use App\Integration\Phrasea\Expose\Sync\ExposeAsset;
use App\Integration\Phrasea\Expose\Sync\ExposeSubDefinition;
use App\Integration\PusherTrait;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Service\Storage\RenditionManager;

final class ExposeSynchronizer
{
    use PusherTrait;
    use LockTrait;

    public function __construct(
        private readonly ExposeClient $exposeClient,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly RenditionManager $renditionManager,
    ) {
    }

    public function synchronize(IntegrationData $basketData): void
    {
        $this->executeWithLock(
            'sync:'.$basketData->getId(),
            30,
            'synchronize',
            fn () => $this->doSynchronize($basketData)
        );
    }

    private function doSynchronize(IntegrationData $basketData): void
    {
        $config = $this->integrationManager->getIntegrationConfiguration($basketData->getIntegration());
        $token = $this->integrationTokenRepository->getLastValidUserToken($config->getIntegrationId(), $basketData->getUserId());
        if (!$token) {
            throw new \InvalidArgumentException('No valid token');
        }

        $publicationId = $basketData->getValue();
        $data = $this->exposeClient->getPublication($config, $token, $publicationId);

        /** @var ExposeAsset[] $exposeAssets */
        $exposeAssets = [];
        foreach ($data['assets'] as $asset) {
            if (!empty($asset['clientAnnotations'])) {
                $annotations = json_decode($asset['clientAnnotations'], true, 512, JSON_THROW_ON_ERROR);
                $basketAssetId = $annotations['basketAssetId'] ?? null;
                if (null !== $basketAssetId) {
                    $subDefinitions = array_map(function (array $subDef): ExposeSubDefinition {
                        $subDefAnnotations = json_decode($subDef['clientAnnotations'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

                        return new ExposeSubDefinition(
                            $subDef['id'],
                            $subDef['name'],
                            $subDefAnnotations['renditionId'] ?? '',
                            $subDefAnnotations['fileId'] ?? '',
                        );
                    }, $asset['subDefinitions']);

                    $exposeAssets[$basketAssetId] = new ExposeAsset(
                        $asset['id'],
                        $basketAssetId,
                        $annotations['fileId'] ?? '',
                        $subDefinitions
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
            $progress($done++);
            $basketAsset = $assetToSync->basketAsset;
            $asset = $basketAsset->getAsset();
            $fileId = $asset->getSource()->getId();

            if (null === $assetToSync->exposeAsset) {
                $exposeAssetId = $this->exposeClient->postAsset($config, $token, $publicationId, $asset, [
                    'clientAnnotations' => json_encode([
                        'basketAssetId' => $basketAsset->getId(),
                        'fileId' => $fileId,
                    ], JSON_THROW_ON_ERROR),
                ]);
            } else {
                $exposeAssetId = $assetToSync->exposeAsset->id;
                if ($assetToSync->exposeAsset->fileId !== $fileId) {
                    $this->exposeClient->deleteAsset($config, $token, $assetToSync->exposeAsset->id);

                    $exposeAssetId = $this->exposeClient->postAsset($config, $token, $publicationId, $asset, [
                        'clientAnnotations' => json_encode([
                            'basketAssetId' => $basketAsset->getId(),
                            'fileId' => $fileId,
                        ], JSON_THROW_ON_ERROR),
                    ]);

                    $assetToSync = new AssetToSync(
                        $basketAsset,
                        new ExposeAsset($exposeAssetId, $basketAsset->getId(), $fileId, [])
                    );
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
                    if (!$rendition->getFile()) {
                        continue;
                    }

                    $existingSubDef = $exposeSubDefs[$renditionName] ?? null;
                    unset($exposeSubDefs[$renditionName]);

                    /** @var ExposeSubDefinition|null $existingSubDef */
                    if (null !== $existingSubDef) {
                        if ($existingSubDef->fileId === $rendition->getFile()->getId()) {
                            continue;
                        }

                        $this->exposeClient->deleteSubDefinition($config, $token, $existingSubDef->id);
                    }

                    $this->exposeClient->postSubDefinition(
                        $config,
                        $token,
                        $exposeAssetId,
                        $renditionName,
                        $rendition,
                        [
                            'clientAnnotations' => json_encode([
                                'renditionId' => $rendition->getId(),
                                'fileId' => $rendition->getFile()->getId(),
                            ], JSON_THROW_ON_ERROR),
                        ]
                    );
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
}
