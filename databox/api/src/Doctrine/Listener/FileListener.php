<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\File\FileDeleteHandler;
use App\Entity\Core\File;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class FileListener extends PostFlushStackListener
{
    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getEntity();

        if ($object instanceof File) {
            if (File::STORAGE_S3_MAIN === $object->getStorage()) {
                $this->addEvent(FileDeleteHandler::createEvent([$object->getPath()]));
            }
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
        ];
    }
}
