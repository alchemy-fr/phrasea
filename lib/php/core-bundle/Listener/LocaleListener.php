<?php

namespace Alchemy\CoreBundle\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class LocaleListener implements EventSubscriberInterface
{
    public function onKernelRequest(KernelEvent $event): void
    {
        if ($locale = $event->getRequest()->getPreferredLanguage()) {
            $event->getRequest()->setLocale($locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 255]],
        ];
    }
}
