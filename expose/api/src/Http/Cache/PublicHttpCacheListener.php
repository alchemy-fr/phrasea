<?php

declare(strict_types=1);

namespace App\Http\Cache;

use App\Entity\Asset;
use App\Entity\Publication;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Marks anonymous reads of open publications and their assets as publicly cacheable.
 *
 * Only the routes purged by ProxyCachePurger are covered: any other route would stay stale
 * in the proxy cache after an update. Must run before Nelmio's CacheableResponseVaryListener
 * (priority -10), which adds "Vary: Origin" to public responses only.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class PublicHttpCacheListener
{
    public const int MAX_AGE = 600;

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (Request::METHOD_GET !== $request->getMethod() || !$response->isSuccessful()) {
            return;
        }

        $publication = match ($request->attributes->get('_route')) {
            Publication::GET_PUBLICATION_ROUTE_NAME => $this->getData($request, Publication::class),
            Asset::GET_ASSET_ROUTE_NAME => $this->getData($request, Asset::class)?->getPublication(),
            default => null,
        };

        if (null === $publication || !$this->isPublicationCacheable($publication)) {
            return;
        }

        if (
            ($request->hasSession() && $request->getSession()->isStarted())
            || $request->headers->has('Authorization')
            // Nested publications are normalized with the passwords sent by the client.
            || $request->headers->has('X-Passwords')
        ) {
            return;
        }

        $maxAge = $this->getMaxAge($publication);

        $response->setCache([
            'public' => true,
            'max_age' => $maxAge,
            's_maxage' => $maxAge,
        ]);
        $response->setVary(['Authorization', 'X-Passwords'], false);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function getData(Request $request, string $class): ?object
    {
        $data = $request->attributes->get('data');

        return $data instanceof $class ? $data : null;
    }

    private function isPublicationCacheable(Publication $publication): bool
    {
        return $publication->isVisible()
            && Publication::SECURITY_METHOD_NONE === $publication->getSecurityContainer()->getSecurityMethod();
    }

    /**
     * Expiration does not update the entity, so nothing would purge the proxy cache.
     */
    private function getMaxAge(Publication $publication): int
    {
        $expiresAt = $publication->getExpiresAt();
        if (null === $expiresAt) {
            return self::MAX_AGE;
        }

        return max(0, min(self::MAX_AGE, $expiresAt->getTimestamp() - time()));
    }
}
