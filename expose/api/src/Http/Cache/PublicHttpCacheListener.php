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
 * Must run before Nelmio's CacheableResponseVaryListener (priority -10), which adds
 * "Vary: Origin" to public responses only.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class PublicHttpCacheListener
{
    public function __construct(private PublicHttpCachePolicy $policy)
    {
    }

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

        if (null !== $publication && $this->policy->isCacheable($request, $publication)) {
            $this->policy->setPublic($response, $publication);
        }
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
}
