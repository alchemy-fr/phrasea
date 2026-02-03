<?php

namespace Alchemy\NotifyBundle\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class NovuManager implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    private bool $novuIsDown = false;

    public function __construct(
        private readonly NovuClient $client,
        #[Autowire(env: 'bool:NOTIFICATIONS_ENABLED')]
        private bool $enabled = true,
    ) {
    }

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void {
        if (!$this->enabled) {
            return;
        }

        $this->client->notifyTopic($topicKey, $authorId, $notificationId, $parameters, $options);
    }

    public function broadcast(
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void {
        if (!$this->enabled) {
            return;
        }

        $this->client->broadcast($notificationId, $parameters, $options);
    }

    public function isSubscribed(string $topicKey, string $subscriberId): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->client->isSubscribed($topicKey, $subscriberId);
    }

    public function createTopic(string $topicKey): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->client->createTopic($topicKey);
    }

    public function addTopicSubscribers(string $topicKey, array $subscribers): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->client->addTopicSubscribers($topicKey, $subscribers);
    }

    public function removeTopicSubscribers(string $topicKey, array $subscribers): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->client->removeTopicSubscribers($topicKey, $subscribers);
    }

    public function upsertSubscribers(array $subscribers): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->client->upsertSubscribers($subscribers);
    }

    public function getTopicSubscriptions(array $topicKeys, string $userId): array
    {
        if (!$this->enabled) {
            return [];
        }

        $data = [];
        foreach ($topicKeys as $topicKey) {
            if (!$this->novuIsDown) {
                try {
                    $isSubscribed = $this->isSubscribed($topicKey, $userId);
                } catch (ExceptionInterface $e) {
                    $this->logger->alert('Novu is down', [
                        'exception' => $e,
                    ]);
                    $isSubscribed = false;
                    $this->novuIsDown = true;
                }
            } else {
                $isSubscribed = false;
            }

            $data[$topicKey] = $isSubscribed;
        }

        return $data;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
