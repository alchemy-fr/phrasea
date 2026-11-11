<?php

declare(strict_types=1);

namespace App\Listener\Doctrine;

use App\Consumer\Handler\DeleteAssetFile;
use App\Entity\Asset;
use App\Repository\UploadLinkRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(Events::postRemove)]
readonly class AssetListener
{
    public function __construct(
        private MessageBusInterface $bus,
        private UploadLinkRepository $uploadLinkRepository,
    ) {
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $asset = $event->getObject();
        if (!$asset instanceof Asset) {
            return;
        }

        // Within the flush transaction: released together with the removal. A committed file stays counted.
        if (!$asset->isCommitted() && null !== $link = $asset->getUploadLink()) {
            $this->uploadLinkRepository->release($link->getId(), $asset->getSize());
        }

        $this->bus->dispatch(new DeleteAssetFile($asset->getPath()));
    }
}
