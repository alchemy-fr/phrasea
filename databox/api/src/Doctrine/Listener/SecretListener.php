<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Integration\WorkspaceSecret;
use App\Security\Secrets\SecretsManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::preUpdate)]
final readonly class SecretListener implements EventSubscriber
{
    public function __construct(
        private SecretsManager $secretsManager
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->encryptValue($args);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->encryptValue($args);
    }

    public function encryptValue(PreUpdateEventArgs|PrePersistEventArgs $args): void
    {
        $object = $args->getObject();
        if ($object instanceof WorkspaceSecret) {
            if (null !== $object->getPlainValue()) {
                $object->setValue($this->secretsManager->encryptSecret($object->getPlainValue()));
                $object->setPlainValue(null);
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }
}
