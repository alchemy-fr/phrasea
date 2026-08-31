<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Manager;

use Alchemy\NotifierBundle\Message\BroadcastNotification;
use Alchemy\NotifierBundle\Message\SendEmailNotification;
use Alchemy\NotifierBundle\Message\SendNotification;
use Alchemy\NotifierBundle\Model\BroadcastOptions;
use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use Alchemy\NotifierBundle\NotifierState;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Public entry point of the bundle: dispatch topic notifications to users or
 * to the followers of an object.
 */
final readonly class NotifierManager
{
    public function __construct(
        private MessageBusInterface $bus,
        private TopicRegistry $topicRegistry,
        private NotifierState $state,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->state->isEnabled();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function notifyUser(string $userId, string $topic, array $params = [], ?NotifyOptions $options = null): void
    {
        $this->notifyUsers([$userId], $topic, $params, $options);
    }

    /**
     * @param array<int, string>   $userIds
     * @param array<string, mixed> $params
     */
    public function notifyUsers(array $userIds, string $topic, array $params = [], ?NotifyOptions $options = null): void
    {
        $this->notify(
            [
                new NotifySelectorDto(
                    userIds: $userIds,
                    topic: new TopicDto(
                        $topic,
                        $params,
                    ),
                ),
            ],
            $options,
        );
    }

    /**
     * Deliver a topic notification to a whole audience.
     *
     * The audience defaults to every user of the identity provider (Keycloak),
     * not only the users already known locally; pass another directory through
     * the options to narrow it down.
     *
     * @param array<string, mixed> $params
     */
    public function broadcast(string $topic, array $params = [], ?BroadcastOptions $options = null): void
    {
        if (!$this->state->isEnabled()) {
            return;
        }

        // Fail fast on an unknown topic, rather than in the worker
        $this->topicRegistry->get($topic);

        $this->bus->dispatch(new BroadcastNotification(
            topic: $topic,
            params: $params,
            channels: $options?->getChannelValues(),
            excludeUserId: $options?->excludeUserId,
            directory: $options?->directory,
        ));
    }

    /**
     * Send a transactional email to a raw address (no subscriber/preferences).
     *
     * @param array<string, mixed> $params
     */
    public function notifyEmail(string $email, string $topic, array $params = [], ?string $locale = null): void
    {
        if (!$this->state->isEnabled()) {
            return;
        }

        // Fail fast on an unknown topic, rather than in the worker
        $this->topicRegistry->get($topic);

        $this->bus->dispatch(new SendEmailNotification($email, $topic, $params, $locale));
    }

    /**
     * Notify by selectors.
     *
     * @param array<int, NotifySelectorDto> $selectors
     */
    public function notify(array $selectors, ?NotifyOptions $options = null): void
    {
        if (!$this->state->isEnabled() || [] === $selectors) {
            return;
        }

        // Fail fast on an unknown topic, rather than in the worker
        foreach ($selectors as $selector) {
            if (null !== $selector->topic) {
                $this->topicRegistry->get($selector->topic->topic);
            }
        }

        $this->bus->dispatch(new SendNotification(
            selectors: $selectors,
            excludeUserId: $options?->excludeUserId,
        ));
    }
}
