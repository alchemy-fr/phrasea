<?php

declare(strict_types=1);

namespace App\Http\Cache;

use App\Entity\Publication;
use App\Security\ShareTokenAuthorizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whether the response about a publication (the publication itself, its assets,
 * their WebVTT) may be kept by the proxy cache, and sets its cache headers accordingly.
 *
 * Only the routes purged by ProxyCachePurger may be made public: any other route would stay
 * stale in the proxy cache after an update.
 */
final readonly class PublicHttpCachePolicy
{
    public const int MAX_AGE = 600;

    /**
     * The request headers the proxy cache key ignores: the nginx configuration bypasses the
     * cache when one of them is sent.
     */
    private const array VARY = ['Authorization', 'X-Passwords', ShareTokenAuthorizer::HEADER_NAME];

    public function isCacheable(Request $request, Publication $publication): bool
    {
        return $publication->isVisible()
            && $publication->isPublic()
            && $this->isAnonymous($request);
    }

    /**
     * A request whose response does not depend on who sends it.
     */
    private function isAnonymous(Request $request): bool
    {
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            return false;
        }

        foreach (self::VARY as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        // A signed URL would stay in the cache once its signature expires: purges target the URL without query.
        return !$request->query->has('jwt');
    }

    public function setPublic(Response $response, Publication $publication): void
    {
        $maxAge = $this->getMaxAge($publication);

        $response->setCache([
            'public' => true,
            'max_age' => $maxAge,
            's_maxage' => $maxAge,
        ]);
        $response->setVary(self::VARY, false);
    }

    public function setPrivate(Response $response): void
    {
        $response->setCache([
            'private' => true,
            'no_store' => true,
        ]);
        $response->setVary(self::VARY, false);
    }

    /**
     * Expiration does not update the entity, so nothing would purge the proxy cache.
     */
    private function getMaxAge(Publication $publication): int
    {
        $visibleUntil = $publication->getVisibleUntil();
        if (null === $visibleUntil) {
            return self::MAX_AGE;
        }

        return max(0, min(self::MAX_AGE, $visibleUntil->getTimestamp() - time()));
    }
}
