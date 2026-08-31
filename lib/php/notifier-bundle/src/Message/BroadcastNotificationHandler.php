<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BroadcastNotificationHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly UserDirectoryRegistry $directoryRegistry,
        private readonly SubscriberManager $subscriberManager,
        private readonly NotificationDeliverer $deliverer,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(BroadcastNotification $message): void
    {
        $directory = $this->directoryRegistry->get($message->directory);
        $channels = null === $message->channels
            ? null
            : array_map(static fn (string $c): ChannelType => ChannelType::from($c), $message->channels);

        $delivered = 0;
        foreach ($directory->iterate() as $user) {
            if (null !== $message->excludeUserId && $user->userId === $message->excludeUserId) {
                continue;
            }

            // One unreachable recipient must not abort the whole broadcast
            try {
                $subscriber = $this->subscriberManager->getOrCreate($user->userId, $user->info);
                $this->deliverer->deliverTo($subscriber, $message->topic, $message->params, channels: $channels);
                ++$delivered;
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Broadcast of topic "%s" failed for user "%s": %s', $message->topic, $user->userId, $e->getMessage()), [
                    'exception' => $e,
                ]);
            }
        }

        $this->logger->info(sprintf('Broadcast of topic "%s" delivered to %d user(s) of directory "%s".', $message->topic, $delivered, $directory->getName()));
    }
}
