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
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockInterface;

final class ExposeSynchronizer
{
    use PusherTrait;
    use LockTrait;

    /**
     * The number of refused assets kept in the synchronization data (all are counted).
     */
    private const int MAX_SKIPPED = 50;

    public function __construct(
        private readonly ExposeClient $exposeClient,
        private readonly ExposeAssetPublisher $publisher,
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly EntityManagerInterface $em,
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

        $publication = ExposeIntegration::readPublicationData($basketData);
        ['id' => $publicationId, 'ignoreRoi' => $ignoreRoi] = $publication;
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

        $skipped = [];
        $skippedTotal = 0;
        foreach ($toSync as $basketAsset) {
            $lock->refresh();
            $progress($done++);
            $basketAssetId = $basketAsset->getId();
            // Not left to the cleaning, even when Expose refuses the asset: publish() already deleted the former version it replaces
            $existing = $exposeAssets[$basketAssetId] ?? null;
            unset($exposeAssets[$basketAssetId]);

            $asset = $basketAsset->getAsset();
            try {
                $this->publisher->publish(
                    $config,
                    $token,
                    $publicationId,
                    $asset,
                    ClipResolver::forBasketAsset($basketAsset, $ignoreRoi),
                    $basketAssetId,
                    ['basketAssetId' => $basketAssetId],
                    $existing,
                );
            } catch (ExposeAssetRefusedException $e) {
                $this->logger->warning('Expose basket synchronization: an asset was refused by Expose, it is not published', [
                    'exception' => $e,
                    'assetId' => $asset->getId(),
                    'basketAssetId' => $basketAssetId,
                    'integrationDataId' => $dataId,
                    'reason' => $e->reason,
                ]);

                ++$skippedTotal;
                if (count($skipped) < self::MAX_SKIPPED) {
                    $skipped[] = [
                        'assetId' => $asset->getId(),
                        'basketAssetId' => $basketAssetId,
                        'title' => $e->title,
                        'reason' => $e->reason,
                    ];
                }
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

        if ($publication['skipped'] !== $skipped || $publication['skippedTotal'] !== $skippedTotal) {
            ExposeIntegration::writePublicationData($basketData, [
                'skipped' => $skipped,
                'skippedTotal' => $skippedTotal,
            ] + $publication);
            $this->em->flush();
        }

        $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
            'id' => $dataId,
            'action' => 'sync-complete',
            'skipped' => $skippedTotal,
        ], direct: true);
    }
}
