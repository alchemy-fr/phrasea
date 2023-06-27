<?php

declare(strict_types=1);

namespace App\Listener;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Asset;
use App\Entity\Publication;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class CacheListener implements EventSubscriberInterface
{
    private const CACHE_ATTR = '_cache';
    public function __construct(private RequestStack $requestStack)
    {
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
        $request = $event->getRequest();
        $method = $request->getMethod();

        if (Request::METHOD_GET !== $method) {
            return;
        }

        if ($object instanceof Publication) {
            if (!$this->isPublicationCacheable($object)) {
                return;
            }
        } elseif ($object instanceof Asset) {
            if (!$this->isPublicationCacheable($object->getPublication())) {
                return;
            }
        } else {
            return;
        }

        try {
            $session = $this->requestStack->getSession();
            if ($session->isStarted()) {
                return;
            }
        } catch (SessionNotFoundException) {
            // continue
        }

        if ($request->headers->has('Authorization')) {
            return;
        }

        $request->attributes->set(self::CACHE_ATTR, [
            's_maxage' => 600,
            'max_age' => 600,
            'public' => true,
        ]);
    }
    private function isPublicationCacheable(Publication $publication): bool
    {
        return $publication->isVisible()
            && Publication::SECURITY_METHOD_NONE === $publication->getSecurityContainer()->getSecurityMethod();
    }
    public function applyCache(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (null !== $cache = $request->attributes->get(self::CACHE_ATTR)) {
            $response = $event->getResponse();

            if ($response->getStatusCode() < 300) {
                $response->setCache($cache);
            }
        }
    }
}
