<?php

namespace App\Integration\Phrasea\Expose;

use App\Entity\Integration\IntegrationBasketData;
use App\Integration\IntegrationManager;
use App\Repository\Integration\IntegrationTokenRepository;

final readonly class ExposeSynchronizer
{
    public function __construct(
        private ExposeClient $exposeClient,
        private IntegrationManager $integrationManager,
        private IntegrationTokenRepository $integrationTokenRepository,
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

        foreach ($basket->getAssets() as $basketAsset) {
            $basketAssetId = $basketAsset->getId();
            if (!isset($assetIds[$basketAssetId])) {
                $this->exposeClient->postAsset($config, $token, $publicationId, $basketAsset->getAsset(), [
                    'clientAnnotations' => json_encode(['basketAssetId' => $basketAssetId], JSON_THROW_ON_ERROR),
                ]);
            } else {
                unset($assetIds[$basketAssetId]);
            }
        }

        foreach ($assetIds as $remoteAssetId) {
            $this->exposeClient->deleteAsset($config, $token, $remoteAssetId);
        }
    }
}
