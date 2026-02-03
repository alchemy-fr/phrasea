<?php

declare(strict_types=1);

namespace Alchemy\NotifyBundle\Message;

use Alchemy\AuthBundle\Repository\UserRepository;
use Alchemy\NotifyBundle\Service\NovuManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UpdateSubscribersHandler
{
    public function __construct(
        private NovuManager $novuManager,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(UpdateSubscribers $message): void
    {
        $userIds = $message->getSubscribers();
        $users = $this->userRepository->getUsersByIds(array_map(fn (string $id): string => $id, $userIds));

        $subscribers = array_map(function (string $userId) use ($users): array {
            $output = ['subscriberId' => $userId];

            $user = $users[$userId] ?? null;
            if (null !== $user) {
                $output['email'] = $user['email'] ?? null;
                if (null === $output['email'] && isset($user['username']) && filter_var($user['username'], FILTER_VALIDATE_EMAIL)) {
                    $output['email'] = $user['username'];
                }

                foreach (['firstName', 'lastName'] as $key) {
                    $output[$key] ??= $user[$key] ?? null;
                }
            }

            return $output;
        }, $userIds);

        $this->novuManager->upsertSubscribers($subscribers);
    }
}
