<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\File\FileDeleteHandler;
use App\Entity\Core\File;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::preRemove)]
readonly class FileListener implements EventSubscriber
{
    public function __construct(private PostFlushStack $postFlushStack)
    {
    }

    public function preRemove(PreRemoveEventArgs $args): void
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
