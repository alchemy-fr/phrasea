<?php

declare(strict_types=1);

namespace App\Listener;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Publication;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CacheListener implements EventSubscriberInterface
{
    private const CACHE_ATTR = '_cache';
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['setCacheHeaders', EventPriorities::PRE_SERIALIZE],
            KernelEvents::RESPONSE => 'applyCache',
        ];
    }

    public function setCacheHeaders(ViewEvent $event): void
    {
        $object = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$object instanceof Publication || Request::METHOD_GET !== $method) {
            return;
        }

        if (
            !$object->isVisible()
            || $object->getSecurityContainer()->getSecurityMethod() !== Publication::SECURITY_METHOD_NONE
        ) {
            return;
        }

        if ($this->session->isStarted()) {
            return;
        }

        $request = $event->getRequest();
        $request->attributes->set(self::CACHE_ATTR, [
            's_maxage' => 600,
            'max_age' => 600,
            'public' => true,
        ]);
    }

    public function applyCache(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (null !== $cache = $request->attributes->get(self::CACHE_ATTR)) {
            $response = $event->getResponse();
            $response->setCache($cache);
        }
    }
}
