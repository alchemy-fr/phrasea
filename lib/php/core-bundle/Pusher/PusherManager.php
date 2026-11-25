<?php

namespace Alchemy\CoreBundle\Pusher;

use Alchemy\CoreBundle\Message\PusherMessage;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class PusherManager
{
    /**
     * Only channels with this prefix go through the authorization endpoint (POST /pusher/auth).
     */
    public const string PRIVATE_PREFIX = 'private-';

    public function __construct(
        private Pusher $pusher,
        private MessageBusInterface $bus,
        private bool $disabled = false,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * A stack deployed without a real-time server has no Sockudo host, as the clients assume.
     */
    public static function isConfigured(Pusher $pusher): bool
    {
        return '' !== ($pusher->getSettings()['host'] ?? '');
    }

    /**
     * A direct push is best-effort: its failure is logged, never thrown, so that
     * it never fails (nor retries) the request or the message sending it.
     */
    public function trigger(string $channel, string $event, array $payload, bool $direct = false): void
    {
        if ($direct) {
            if (!$this->disabled && self::isConfigured($this->pusher)) {
                $channel = $this->normalizeChannel($channel);
                try {
                    $this->pusher->trigger($channel, $event, $payload);
                } catch (PusherException|GuzzleException $e) {
                    $this->logFailure($e, $channel, $event);
                }
            }

            return;
        }

        $this->bus->dispatch($this->createBusMessage($channel, $event, $payload));
    }

    private function logFailure(PusherException|GuzzleException $e, string $channel, string $event): void
    {
        $level = match (true) {
            // Skipped while the circuit breaker is open: one log per push would only be noise
            $e instanceof CircuitOpenException || $e->getPrevious() instanceof CircuitOpenException => LogLevel::DEBUG,
            // Transport failure (timeout, connection refused): it opens the circuit breaker
            $e instanceof NetworkExceptionInterface || ($e instanceof ApiErrorException && 0 === $e->getCode()) => LogLevel::WARNING,
            // Sockudo answered with an error (invalid key, payload too large…)
            default => LogLevel::ERROR,
        };

        $this->logger->log($level, 'Real-time push of {event} on {channel} failed: {error}', [
            'event' => $event,
            'channel' => $channel,
            'error' => $e->getMessage(),
            'exception' => $e,
        ]);
    }

    /**
     * Returns the Pusher/Sockudo authorization signature for a private (or
     * presence) channel subscription. The caller is responsible for verifying
     * that the current user is actually allowed to subscribe to $channel.
     */
    public function authorizeChannel(string $channel, string $socketId): string
    {
        return $this->pusher->authorizeChannel($channel, $socketId);
    }

    public function normalizeChannel(string $channel): string
    {
        return self::normalizeChannelName($channel);
    }

    public static function normalizeChannelName(string $channel): string
    {
        return preg_replace('/[^a-z0-9_\-=@,.;]/i', '.', $channel);
    }

    public function createBusMessage(string $channel, string $event, array $payload): PusherMessage
    {
        return new PusherMessage($channel, $event, $payload);
    }
}
