<?php

declare(strict_types=1);

namespace App\Listener\Doctrine;

use App\Consumer\Handler\DeleteAssetFile;
use App\Entity\Asset;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(Events::postRemove)]
readonly class AssetListener implements EventSubscriber
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $asset = $event->getObject();
        if (!$asset instanceof Asset) {
            return;
        }

        $this->bus->dispatch(new DeleteAssetFile($asset->getPath()));
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postRemove,
        ];
    }
}
