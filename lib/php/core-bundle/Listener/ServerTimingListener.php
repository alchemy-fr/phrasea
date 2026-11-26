<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Listener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tells where the time of a request goes: a Server-Timing header (shown by the browser
 * devtools) and a warning for the requests slower than SLOW_REQUEST_LOG_THRESHOLD_MS.
 *
 * The "app" duration starts when PHP gets the request: the time spent waiting for a free
 * PHP-FPM worker is not in it, compare it to the upstream time logged by NGINX.
 */
#[AsEventListener(KernelEvents::RESPONSE, method: 'onResponse', priority: -1000)]
#[AsEventListener(KernelEvents::TERMINATE, method: 'onTerminate', priority: -2048)]
final readonly class ServerTimingListener
{
    public function __construct(
        private ServerTimingCollector $collector,
        private LoggerInterface $logger,
        #[Autowire('%env(int:SLOW_REQUEST_LOG_THRESHOLD_MS)%')]
        private int $slowRequestThresholdMs,
    ) {
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $metrics = [sprintf('app;dur=%.1f', $this->getDuration($event->getRequest()))];
        foreach ($this->collector->getEntries() as $name => $entry) {
            $metric = sprintf('%s;dur=%.1f', $name, $entry['dur']);
            if (null !== $entry['desc']) {
                $metric .= sprintf(';desc="%d %s"', $entry['count'], $entry['desc']);
            }
            $metrics[] = $metric;
        }

        $event->getResponse()->headers->set('Server-Timing', implode(', ', $metrics), false);
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if ($this->slowRequestThresholdMs <= 0) {
            return;
        }

        $request = $event->getRequest();
        $duration = $this->getDuration($request);
        if ($duration < $this->slowRequestThresholdMs) {
            return;
        }

        $timings = [];
        foreach ($this->collector->getEntries() as $name => $entry) {
            $timings[$name] = [
                'duration_ms' => (int) round($entry['dur']),
                'count' => $entry['count'],
            ];
        }

        // The query string is left out: it may hold the terms of a search.
        $this->logger->warning('Slow request', [
            'method' => $request->getMethod(),
            'route' => $request->attributes->get('_route'),
            'path' => $request->getPathInfo(),
            'status' => $event->getResponse()->getStatusCode(),
            'duration_ms' => (int) round($duration),
            'timings' => $timings,
            'memory_peak' => memory_get_peak_usage(),
        ]);
    }

    private function getDuration(Request $request): float
    {
        $start = (float) $request->server->get('REQUEST_TIME_FLOAT', microtime(true));

        return max(0.0, (microtime(true) - $start) * 1000);
    }
}
