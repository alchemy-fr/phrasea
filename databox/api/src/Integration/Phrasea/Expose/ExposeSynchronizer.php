<?php

namespace App\Integration\Phrasea\Expose;

use App\Entity\Integration\IntegrationBasketData;
use App\Integration\IntegrationManager;
use App\Integration\PusherTrait;
use App\Repository\Integration\IntegrationTokenRepository;

final class ExposeSynchronizer
{
    use PusherTrait;

    public function __construct(
        private readonly ExposeClient $exposeClient,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
    )
    {
    }

    public function synchronize(IntegrationBasketData $basketData): void
    {
        $config = $this->integrationManager->getIntegrationConfiguration($basketData->getIntegration());
        $token = $this->integrationTokenRepository->getLastValidUserToken($config->getIntegrationId(), $basketData->getUserId());
        if (!$token) {
            throw new \InvalidArgumentException('No valid token');
        }

        $publicationId = $basketData->getValue();
        $data = $this->exposeClient->getPublication($config, $token, $publicationId);

        $assetIds = [];
        foreach ($data['assets'] as $asset) {
            if (!empty($asset['clientAnnotations'])) {
                $annotations = json_decode($asset['clientAnnotations'], true, 512, JSON_THROW_ON_ERROR);
                $basketAssetId = $annotations['basketAssetId'] ?? null;
                if (null !== $basketAssetId) {
                    $assetIds[$basketAssetId] = $asset['id'];
                }
            }
        }

        $basket = $basketData->getObject();

        $toAdd = [];

        foreach ($basket->getAssets() as $basketAsset) {
            $basketAssetId = $basketAsset->getId();
            if (!isset($assetIds[$basketAssetId])) {
                $toAdd[] = $basketAsset;
            } else {
                unset($assetIds[$basketAssetId]);
            }
        }

        $total = count($toAdd);
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

        foreach ($toAdd as $basketAsset) {
            $progress($done++);
            $this->exposeClient->postAsset($config, $token, $publicationId, $basketAsset->getAsset(), [
                'clientAnnotations' => json_encode(['basketAssetId' => $basketAsset->getId()], JSON_THROW_ON_ERROR),
            ]);
        }
        $progress($total);

        $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
            'id' => $dataId,
            'action' => 'sync-clean',
        ], direct: true);
        foreach ($assetIds as $remoteAssetId) {
            $this->exposeClient->deleteAsset($config, $token, $remoteAssetId);
        }

        $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
            'id' => $dataId,
            'action' => 'sync-complete',
        ], direct: true);
    }
}
