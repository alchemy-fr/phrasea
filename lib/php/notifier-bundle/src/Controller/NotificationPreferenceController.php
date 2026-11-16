<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Security\CurrentSubscriberResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationPreferenceController
{
    /**
     * Far above the number of (topic, channel) pairs an app declares.
     */
    public const int MAX_ITEMS = 100;

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

        if (count($entries) > self::MAX_ITEMS) {
            throw new BadRequestHttpException(sprintf('At most %d preferences can be updated at once.', self::MAX_ITEMS));
        }

        $preferences = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['topic'], $entry['channel'], $entry['enabled'])) {
                throw new BadRequestHttpException('Each preference requires "topic", "channel" and "enabled".');
            }
            if (!is_string($entry['topic']) || !is_string($entry['channel'])) {
                throw new BadRequestHttpException('"topic" and "channel" must be strings.');
            }
            if (!is_bool($entry['enabled'])) {
                throw new BadRequestHttpException('"enabled" must be a boolean.');
            }

            $preferences[] = [
                'topic' => $entry['topic'],
                'channel' => ChannelType::tryFrom($entry['channel'])
                    ?? throw new BadRequestHttpException(sprintf('Unknown channel "%s".', $entry['channel'])),
                'enabled' => $entry['enabled'],
            ];
        }

        try {
            $this->preferenceManager->setPreferences($subscriber, $preferences);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        } catch (UniqueConstraintViolationException $e) {
            // The EntityManager is closed from here on: nothing else may touch the database.
            throw new ConflictHttpException('Preferences were changed concurrently, retry.', $e);
        }

        return new JsonResponse([
            'items' => $this->preferenceManager->getEffectivePreferences($subscriber),
        ]);
    }
}
