<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Search\Mapping\UpdateAttributesMappingHandler;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class AttributeMappingListener implements EventSubscriber
{
    use ChangeFieldListenerTrait;

    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

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
        $this->postFlushStack->addEvent(new EventMessage(UpdateAttributesMappingHandler::EVENT, [
            'id' => $workspaceId,
        ]));
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postUpdate => 'postUpdate',
            Events::postPersist => 'postPersist',
        ];
    }
}
