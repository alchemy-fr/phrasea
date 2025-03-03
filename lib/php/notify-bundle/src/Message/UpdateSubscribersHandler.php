<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifyBundle\Service\NovuClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateSubscribersHandler
{
    public function __construct(
        private NovuClient $novuClient,
        private UserRepository $userRepository,
    )
    {
    }

    public function __invoke(UpdateSubscribers $message): void
    {
        $subscribers = array_map(function (string $subscriber): array {
            $subscriber = ['subscriberId' => $subscriber];

            $user = $this->userRepository->getUser($subscriber['subscriberId']);
            if (null !== $user) {
                $subscriber['email'] = $user['email'] ?? null;
                if (null === $subscriber['email'] && isset($user['username']) && filter_var($user['username'], FILTER_VALIDATE_EMAIL)) {
                    $subscriber['email'] = $user['username'];
                }

                foreach (['firstName', 'lastName'] as $key) {
                    $subscriber[$key] ??= $user[$key] ?? null;
                }
            }

            return $subscriber;
        }, $message->getSubscribers());

        $this->novuClient->upsertSubscribers($subscribers);

    }
}
