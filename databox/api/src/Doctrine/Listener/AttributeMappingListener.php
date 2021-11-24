<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Search\Mapping\UpdateAttributesMappingHandler;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class AttributeMappingListener extends PostFlushStackListener
{
    use ChangeFieldListenerTrait;

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof Workspace) {
            if ($this->hasChangedField([
                'enabledLocales',
            ], $args->getEntityManager(), $entity)) {
                $this->updateWorkspace($entity->getId());
            }
        } elseif ($entity instanceof AttributeDefinition) {
            if ($this->hasChangedField([
                'fieldType',
                'name',
                'searchable',
            ], $args->getEntityManager(), $entity)) {
                $this->updateWorkspace($entity->getWorkspaceId());
            }
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof AttributeDefinition) {
            $this->updateWorkspace($entity->getWorkspaceId());
        }
    }

    public function updateWorkspace(string $workspaceId): void
    {
        $this->addEvent(new EventMessage(UpdateAttributesMappingHandler::EVENT, [
            'id' => $workspaceId,
        ]));
    }

    public function getSubscribedEvents()
    {
        return array_merge(parent::getSubscribedEvents(), [
            Events::postUpdate => 'postUpdate',
            Events::postPersist => 'postPersist',
        ]);
    }
}
