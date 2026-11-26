<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Service\Asset\Attribute\AssetNameResolver;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Tells the owner of an asset that it was put in quarantine.
 *
 * The owner can always read their quarantined asset (AssetVoter::QUARANTINE):
 * no read check is needed.
 */
#[AsMessageHandler]
final readonly class NotifyAssetQuarantinedHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotifierManager $notifierManager,
        private AssetNameResolver $assetNameResolver,
    ) {
    }

    public function __invoke(NotifyAssetQuarantined $message): void
    {
        $asset = $this->em->find(Asset::class, $message->getAssetId());
        // The quarantine may have been lifted (bypass, re-evaluation) before this message was handled
        if (null === $asset
            || $asset->isDeleted()
            || AssetStatusEnum::Quarantined !== $asset->getStatus()
        ) {
            return;
        }

        $ownerId = $asset->getOwnerId();
        // The notifier keys its subscribers by user UUID: any other owner id cannot be notified
        if (null === $ownerId || !Uuid::isValid($ownerId)) {
            return;
        }

        $assetId = $asset->getId();
        $workspace = $asset->getWorkspace();

        $this->notifierManager->notifyUser($ownerId, Asset::EVENT_QUARANTINED, [
            'assetId' => $assetId,
            'name' => $this->assetNameResolver->resolveNameAsString($asset) ?? $assetId,
            'workspaceId' => $workspace->getId(),
            'workspaceName' => $workspace->getName(),
            'url' => '/quarantine/'.$assetId,
        ]);
    }
}
