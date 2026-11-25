<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Pusher;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle middleware suspending the requests to Sockudo for a while after a
 * network failure (connection refused, timeout…), so that the following pushes
 * do not each wait for the timeout again.
 *
 * Its state lives as long as the process: a worker keeps it from one message to
 * the next. Any HTTP response, even an error one, closes the circuit: the
 * server answered.
 */
final class CircuitBreakerMiddleware
{
    private ?float $openUntil = null;

    /**
     * @param int                      $cooldown seconds during which the requests are rejected after a network failure
     * @param (\Closure(): float)|null $now      current time in seconds, microtime(true) by default
     */
    public function __construct(
        private readonly int $cooldown = 30,
        private readonly ?\Closure $now = null,
    ) {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $now = $this->now();
            if (null !== $this->openUntil && $now < $this->openUntil) {
                return Create::rejectionFor(new CircuitOpenException(sprintf(
                    'Sockudo unreachable, real-time pushes suspended for %d s',
                    (int) ceil($this->openUntil - $now),
                ), $request));
            }

            return $handler($request, $options)->then(
                function (ResponseInterface $response): ResponseInterface {
                    $this->openUntil = null;

                    return $response;
                },
                function (mixed $reason): PromiseInterface {
                    if ($reason instanceof NetworkExceptionInterface) {
                        $this->openUntil = $this->now() + $this->cooldown;
                    }

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    private function now(): float
    {
        return null !== $this->now ? ($this->now)() : microtime(true);
    }
}
