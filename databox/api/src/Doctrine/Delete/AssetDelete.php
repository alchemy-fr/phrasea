<?php

declare(strict_types=1);

namespace App\Doctrine\Delete;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use App\Elasticsearch\ElasticSearchClient;
use App\Entity\Core\Asset;
use App\Model\AuditLogActionEnum;
use App\Service\Log\AuditLogManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssetDelete
{
    public function __construct(
        private EntityManagerInterface $em,
        private ElasticSearchClient $elasticSearchClient,
        private AuditLogManager $auditLogManager,
    ) {
    }

    /**
     * @param string|null $reason why the assets are deleted, when not on request (e.g. "trash_retention")
     */
    public function deleteAssets(array $assetIds, ?string $reason = null): void
    {
        $assets = DoctrineUtil::iterateIds($this->em->getRepository(Asset::class), $assetIds);
        $deleted = [];
        DeferredIndexListener::disable();
        try {
            // One audit entry per asset, not per attribute, rendition... it cascades to
            $this->auditLogManager->mute(function () use ($assets, &$deleted): void {
                foreach ($assets as $asset) {
                    $deleted[] = [
                        $asset->getId(),
                        $this->auditLogManager->resolveLabel($asset, loadAttributes: true),
                        $asset->getWorkspaceId(),
                    ];
                    $this->em->remove($asset);
                }
                $this->em->flush();
            });

            foreach ($deleted as [$id, $label, $workspaceId]) {
                $this->auditLogManager->logRaw(AuditLogActionEnum::ObjectDeleted, Asset::class, $id, $label, $workspaceId, array_filter([
                    'reason' => $reason,
                ]));
            }
            $this->em->flush();
        } finally {
            DeferredIndexListener::enable();
        }

        $this->elasticSearchClient->deleteByQuery(
            'asset',
            [
                'terms' => [
                    '_id' => $assetIds,
                ],
            ],
        );
    }
}
