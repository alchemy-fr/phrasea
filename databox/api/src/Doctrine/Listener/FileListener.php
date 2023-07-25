<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\File\FileDeleteHandler;
use App\Entity\Core\File;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class FileListener implements EventSubscriber
{
    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if ($object instanceof File) {
            if (File::STORAGE_S3_MAIN === $object->getStorage()) {
                $this->postFlushStack->addEvent(FileDeleteHandler::createEvent([$object->getPath()]));
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preRemove,
        ];
    }
}
