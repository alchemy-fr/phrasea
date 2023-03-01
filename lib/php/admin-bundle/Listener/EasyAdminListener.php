<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Listener;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Translation\TranslatableMessage;

class EasyAdminListener implements EventSubscriberInterface
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['flashMessageAfterPersist'],
            AfterEntityUpdatedEvent::class => ['flashMessageAfterUpdate'],
            AfterEntityDeletedEvent::class => ['flashMessageAfterDelete'],
        ];
    }

    public function flashMessageAfterPersist(AfterEntityPersistedEvent $event): void
    {
        $this->session->getFlashBag()->add('success', new TranslatableMessage('content_admin.flash_message.create', [
            '%name%' => $this->getObjectName($event->getEntityInstance()),
        ], 'admin'));
    }

    public function flashMessageAfterUpdate(AfterEntityUpdatedEvent $event): void
    {
        $this->session->getFlashBag()->add('success', new TranslatableMessage('content_admin.flash_message.update', [
            '%name%' => $this->getObjectName($event->getEntityInstance()),
        ], 'admin'));
    }

    public function flashMessageAfterDelete(AfterEntityDeletedEvent $event): void
    {
        $this->session->getFlashBag()->add('success', new TranslatableMessage('content_admin.flash_message.delete', [], 'admin'));
    }

    private function getObjectName(object $object): string
    {
        if (method_exists($object, '__toString')) {
            return (string) $object;
        } elseif (method_exists($object, 'getId')) {
            return (string) $object->getId();
        }

        return 'Object';
    }
}
