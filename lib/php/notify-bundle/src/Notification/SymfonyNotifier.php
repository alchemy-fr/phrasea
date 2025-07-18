<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Notification;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifyBundle\Message\AddTopicSubscribers;
use Alchemy\NotifyBundle\Message\NotifyTopic;
use Alchemy\NotifyBundle\Message\RemoveTopicSubscribers;
use Alchemy\NotifyBundle\Message\UpdateSubscribers;
use Alchemy\NotifyBundle\Service\NovuClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Bridge\Novu\NovuSubscriberRecipient;
use Symfony\Component\Notifier\NotifierInterface as SymfonyNotifierInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class SymfonyNotifier implements NotifierInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private bool $enabled = true;

    public function __construct(
        private readonly SymfonyNotifierInterface $notifier,
        private readonly MessageBusInterface $bus,
        private readonly NovuClient $novuClient,
        private UserRepository $userRepository,
        private RequestStack $requestStack,
        private readonly bool $notifyAuthor = false,
        private bool $novuIsDown = false,
    ) {
    }

    public function notifyUser(string $userId, string $notificationId, array $parameters = []): void
    {
        $recipient = new NovuSubscriberRecipient($userId);
        $this->sendNotification($recipient, $notificationId, $parameters);
    }

    public function broadcast(string $notificationId, array $parameters = []): void
    {
        $this->novuClient->broadcast($notificationId, $parameters);
    }

    public function sendEmail(string $email, string $notificationId, array $parameters = []): void
    {
        $recipient = new NovuSubscriberRecipient($email, email: $email);
        $this->sendNotification($recipient, $notificationId, $parameters);
    }

    private function sendNotification(NovuSubscriberRecipient $recipient, string $notificationId, array $parameters): void
    {
        $content = json_encode($parameters, JSON_THROW_ON_ERROR);
        $this->logger->debug(sprintf('Send notification "%s" with template "%s"', $recipient->getSubscriberId(), $notificationId), [
            'content' => $content,
        ]);

        $notification = new NovuNotification($notificationId);
        $notification->content($content);

        $this->notifier->send($notification, $recipient);
    }

    public function notifyTopic(
        string $topicKey,
        ?string $authorId,
        string $notificationId,
        array $parameters = [],
        array $options = [],
    ): void {
        if ($this->notifyAuthor) {
            $authorId = null;
        }
        $this->bus->dispatch(new NotifyTopic($topicKey, $authorId, $notificationId, $parameters, $options));
    }

    public function addTopicSubscribers(string $topicKey, array $subscribers, bool $direct = false): void
    {
        if ($direct) {
            $this->novuClient->addTopicSubscribers($topicKey, $subscribers);
        } else {
            $this->bus->dispatch(new AddTopicSubscribers($topicKey, $subscribers));
        }
        $this->bus->dispatch(new UpdateSubscribers($subscribers));
    }

    public function createTopic(string $topicKey): void
    {
        $this->novuClient->createTopic($topicKey);
    }

    public function removeTopicSubscribers(string $topicKey, array $subscribers): void
    {
        $this->bus->dispatch(new RemoveTopicSubscribers($topicKey, $subscribers));
    }

    public function getTopicSubscriptions(array $topicKeys, string $userId): array
    {
        $data = [];

        foreach ($topicKeys as $topicKey) {
            if (!$this->novuIsDown) {
                try {
                    $isSubscribed = $this->novuClient->isSubscribed($topicKey, $userId);
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

    public function getUsername(string $userId): string
    {
        $user = $this->userRepository->getUser($userId);

        return $user ? $user['username'] : 'Deleted User';
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !$this->requestStack->getCurrentRequest()?->headers->get('X-Notification-Disabled');
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
