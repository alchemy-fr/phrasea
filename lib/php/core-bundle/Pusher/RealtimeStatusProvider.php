<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Pusher;

use Pusher\ApiErrorException;
use Pusher\Pusher;

/**
 * Reads the occupied channels of the Sockudo application.
 *
 * Sockudo does not list the connections with an identity: connected users are
 * deduced from their own channel (`{userChannelPrefix}{userId}`), to which the
 * clients subscribe for the in-app notifications.
 */
final readonly class RealtimeStatusProvider
{
    private const array CHANNEL_TYPE_PREFIXES = ['private-', 'presence-'];

    public function __construct(
        private Pusher $pusher,
        private string $userChannelPrefix = PusherManager::PRIVATE_PREFIX.'user-',
        private bool $disabled = false,
    ) {
    }

    public function getStatus(): RealtimeStatus
    {
        $appId = (string) ($this->pusher->getSettings()['app_id'] ?? '');
        $appId = '' !== $appId ? $appId : null;

        if ($this->disabled) {
            return new RealtimeStatus(enabled: false, reachable: false, appId: $appId);
        }

        $start = hrtime(true);
        try {
            $channels = $this->fetchChannels();
        } catch (\Throwable $e) {
            return new RealtimeStatus(
                enabled: true,
                reachable: false,
                appId: $appId,
                error: $e instanceof ApiErrorException ? (string) $e : $e->getMessage(),
            );
        }
        $latencyMs = round((hrtime(true) - $start) / 1_000_000, 1);

        $userPrefix = PusherManager::normalizeChannelName($this->userChannelPrefix);
        $users = [];
        $channelsByType = [];
        $subscriptionCount = 0;

        foreach ($channels as $name => $info) {
            $name = (string) $name;
            $count = self::getSubscriptionCount($info);
            $subscriptionCount = self::sum($subscriptionCount, $count);

            if ('' !== $userPrefix && str_starts_with($name, $userPrefix) && strlen($name) > strlen($userPrefix)) {
                $userId = substr($name, strlen($userPrefix));
                $users[$userId] = array_key_exists($userId, $users) ? self::sum($users[$userId], $count) : $count;
            }

            $type = self::getChannelType($name);
            $channelsByType[$type] ??= ['channels' => 0, 'subscriptions' => 0];
            ++$channelsByType[$type]['channels'];
            $channelsByType[$type]['subscriptions'] = self::sum($channelsByType[$type]['subscriptions'], $count);
        }

        ksort($channelsByType, SORT_STRING);

        return new RealtimeStatus(
            enabled: true,
            reachable: true,
            appId: $appId,
            latencyMs: $latencyMs,
            channelCount: count($channels),
            subscriptionCount: $subscriptionCount,
            channelsByType: $channelsByType,
            users: $users,
        );
    }

    /**
     * @return array<string|int, mixed> channel name => channel info
     */
    private function fetchChannels(): array
    {
        try {
            $result = $this->pusher->getChannels(['info' => 'subscription_count']);
        } catch (ApiErrorException $e) {
            // A server which does not support this info answers 400: the channels are still worth listing.
            if ($e->getCode() < 400 || $e->getCode() >= 500) {
                throw $e;
            }

            $result = $this->pusher->getChannels();
        }

        $channels = $result->channels ?? [];

        return is_object($channels) ? get_object_vars($channels) : (array) $channels;
    }

    private static function getSubscriptionCount(mixed $info): ?int
    {
        $count = is_object($info) ? ($info->subscription_count ?? null) : (is_array($info) ? ($info['subscription_count'] ?? null) : null);

        return is_numeric($count) ? (int) $count : null;
    }

    /**
     * `private-workspace-<uuid>` → `workspace`, `private-thread-asset.<uuid>` → `thread`.
     */
    private static function getChannelType(string $name): string
    {
        foreach (self::CHANNEL_TYPE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }

        return explode('-', $name, 2)[0];
    }

    /**
     * A total is unknown as soon as one of its parts is.
     */
    private static function sum(?int $a, ?int $b): ?int
    {
        return null === $a || null === $b ? null : $a + $b;
    }
}
