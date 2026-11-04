<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Security\CurrentSubscriberResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationPreferenceController
{
    public function __construct(
        private readonly CurrentSubscriberResolver $currentSubscriber,
        private readonly PreferenceManager $preferenceManager,
    ) {
    }

    #[Route('/notification-preferences', name: 'alchemy_notifier_preferences_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $subscriber = $this->currentSubscriber->getSubscriber();

        return new JsonResponse([
            'items' => $this->preferenceManager->getEffectivePreferences($subscriber),
        ]);
    }

    #[Route('/notification-preferences', name: 'alchemy_notifier_preferences_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $subscriber = $this->currentSubscriber->getSubscriber();

        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $entries = $payload['items'] ?? (isset($payload['topic']) ? [$payload] : null);
        if (!is_array($entries)) {
            throw new BadRequestHttpException('Expected an "items" array or a single preference object.');
        }

        foreach ($entries as $entry) {
            if (!isset($entry['topic'], $entry['channel'], $entry['enabled'])) {
                throw new BadRequestHttpException('Each preference requires "topic", "channel" and "enabled".');
            }

            $channel = ChannelType::tryFrom((string) $entry['channel'])
                ?? throw new BadRequestHttpException(sprintf('Unknown channel "%s".', $entry['channel']));

            $this->preferenceManager->setPreference(
                $subscriber,
                (string) $entry['topic'],
                $channel,
                (bool) $entry['enabled'],
            );
        }

        return new JsonResponse([
            'items' => $this->preferenceManager->getEffectivePreferences($subscriber),
        ]);
    }
}
