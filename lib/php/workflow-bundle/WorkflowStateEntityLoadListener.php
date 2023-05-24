<?php

declare(strict_types=1);

namespace Alchemy\WorkflowBundle;

use Alchemy\Workflow\Doctrine\Entity\WorkflowState;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

final class WorkflowStateEntityLoadListener implements EventSubscriberInterface
{
    public function __construct()
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $class = $args->getClassMetadata();

        if ($class->getName() === WorkflowState::class) {
            $class->isMappedSuperclass = true;
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
        ];
    }
}
