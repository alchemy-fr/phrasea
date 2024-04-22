<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Search\Mapping\UpdateAttributesMapping;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::postUpdate)]
#[AsDoctrineListener(Events::postPersist)]
class AttributeMappingListener implements EventSubscriber
{
    use ChangeFieldListenerTrait;

    public function __construct(private readonly PostFlushStack $postFlushStack)
    {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Workspace) {
            if ($this->hasChangedField([
                'enabledLocales',
            ], $args->getObjectManager(), $entity)) {
                $this->updateWorkspace($entity->getId());
            }
        } elseif ($entity instanceof AttributeDefinition) {
            if ($this->hasChangedField([
                'fieldType',
                'name',
                'searchable',
                'suggest',
            ], $args->getObjectManager(), $entity)) {
                $this->updateWorkspace($entity->getWorkspaceId());
            }
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AttributeDefinition) {
            $this->updateWorkspace($entity->getWorkspaceId());
        }
    }

    public function updateWorkspace(string $workspaceId): void
    {
        $this->postFlushStack->addBusMessage(new UpdateAttributesMapping($workspaceId));
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
            Events::postPersist,
        ];
    }
}
