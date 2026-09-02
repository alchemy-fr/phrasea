<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Repository\BroadcastRepository;
use Alchemy\NotifierBundle\Subscriber\UserDirectoryRegistry;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly BroadcastRepository $broadcastRepository,
        private readonly EntityManagerInterface $em,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(BroadcastNotification $message): void
    {
        $broadcast = $this->broadcastRepository->find($message->broadcastId);
        if (null === $broadcast) {
            // Purged (or rolled back) between the dispatch and the worker
            $this->logger->error(sprintf('No broadcast "%s" to deliver.', $message->broadcastId));

            return;
        }

        $directory = $this->directoryRegistry->get($broadcast->getDirectory());
        $channels = null === $broadcast->getChannels()
            ? null
            : array_map(static fn (string $c): ChannelType => ChannelType::from($c), $broadcast->getChannels());

        $topic = $broadcast->getTopic();
        $params = $broadcast->getPayload();
        $excludeUserId = $broadcast->getExcludeUserId();

        $broadcast->start();
        $this->em->flush();

        $delivered = 0;
        $failed = 0;

        foreach ($directory->iterate() as $user) {
            if (null !== $excludeUserId && $user->userId === $excludeUserId) {
                continue;
            }

            // One unreachable recipient must not abort the whole broadcast
            try {
                $subscriber = $this->subscriberManager->getOrCreate($user->userId, $user->info);
                $this->deliverer->deliverTo($subscriber, $topic, $params, channels: $channels);
                ++$delivered;
            } catch (\Throwable $e) {
                ++$failed;
                $this->logger->error(sprintf('Broadcast of topic "%s" failed for user "%s": %s', $topic, $user->userId, $e->getMessage()), [
                    'exception' => $e,
                ]);
            }
        }

        $broadcast->complete($delivered, $failed);
        $this->em->flush();

        $this->logger->info(sprintf('Broadcast of topic "%s" delivered to %d user(s) of directory "%s" (%d failure(s)).', $topic, $delivered, $directory->getName(), $failed));
    }
}
