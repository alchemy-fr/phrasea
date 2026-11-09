<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

use Alchemy\CoreBundle\Lock\LockTrait;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Integration\IntegrationData;
use App\Integration\Auth\MissingIntegrationTokenException;
use App\Integration\IntegrationManager;
use App\Integration\PusherTrait;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Service\Clip\ClipResolver;
use Symfony\Component\Lock\LockInterface;

final class ExposeSynchronizer
{
    use PusherTrait;
    use LockTrait;

    public function __construct(
        private readonly ExposeClient $exposeClient,
        private readonly ExposeAssetPublisher $publisher,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
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

        // The assets published by other means (e.g. an automation) have no basket item: they are left untouched
        $exposeAssets = $this->publisher->readPublicationAssets(
            $data,
            fn (array $annotations): ?string => isset($annotations['basketAssetId']) ? (string) $annotations['basketAssetId'] : null,
        );

        /** @var Basket $basket */
        $basket = $basketData->getObject();

        /** @var BasketAsset[] $toSync */
        $toSync = [];
        foreach ($basket->getAssets() as $basketAsset) {
            if (!$basketAsset->getAsset()->getSource()) {
                continue;
            }

            $toSync[] = $basketAsset;
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

        foreach ($toSync as $basketAsset) {
            $lock->refresh();
            $progress($done++);
            $basketAssetId = $basketAsset->getId();
            $existing = $exposeAssets[$basketAssetId] ?? null;
            unset($exposeAssets[$basketAssetId]);

            $this->publisher->publish(
                $config,
                $token,
                $publicationId,
                $basketAsset->getAsset(),
                ClipResolver::forBasketAsset($basketAsset, $ignoreRoi),
                $basketAssetId,
                ['basketAssetId' => $basketAssetId],
                $existing,
            );
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
