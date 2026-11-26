<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use Alchemy\NotifierBundle\NotifierState;
use App\Consumer\Handler\Asset\NotifyAssetQuarantined;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Notifies the owner of an asset when it enters the quarantine, whichever worker put it there
 * (file analysis, re-evaluation, workflow actions).
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Asset::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Asset::class)]
final readonly class AssetQuarantineListener
{
    use ChangeFieldListenerTrait;

    public function __construct(
        private PostFlushStack $postFlushStack,
        private NotifierState $notifierState,
    ) {
    }

    public function postPersist(Asset $asset, PostPersistEventArgs $args): void
    {
        if (AssetStatusEnum::Quarantined === $asset->getStatus()) {
            $this->notify($asset);
        }
    }

    public function postUpdate(Asset $asset, PostUpdateEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if (!$this->hasChangedField(['status'], $em, $asset)) {
            return;
        }

        [$old] = $this->getChangedSetFor('status', $em, $asset);
        // The change set may hold the raw column value instead of the enum case
        if (!$old instanceof AssetStatusEnum) {
            $old = AssetStatusEnum::tryFrom((int) $old);
        }

        if (AssetStatusEnum::Quarantined === $asset->getStatus() && AssetStatusEnum::Quarantined !== $old) {
            $this->notify($asset);
        }
    }

    private function notify(Asset $asset): void
    {
        if (!$this->notifierState->isEnabled() || $asset->isDeleted()) {
            return;
        }

        $this->postFlushStack->addBusMessage(new NotifyAssetQuarantined($asset->getId()));
    }
}
