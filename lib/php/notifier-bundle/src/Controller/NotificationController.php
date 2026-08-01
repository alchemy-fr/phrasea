<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Controller;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Alchemy\NotifierBundle\Repository\NotificationRepository;
use Alchemy\NotifierBundle\Security\CurrentSubscriberResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationController
{
    public function __construct(
        private readonly CurrentSubscriberResolver $currentSubscriber,
        private readonly NotificationRepository $repository,
        private readonly NotificationRenderer $renderer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/notifications', name: 'alchemy_notifier_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $subscriber = $this->currentSubscriber->getSubscriber();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $unreadOnly = $request->query->getBoolean('unread');

        $qb = $this->repository->createForSubscriberQueryBuilder($subscriber, $unreadOnly ?: null)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
        ;

        $paginator = new Paginator($qb, false);
        $total = count($paginator);

        $items = [];
        foreach ($paginator as $notification) {
            $items[] = $this->normalize($notification);
        }

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'unreadCount' => $this->repository->countUnread($subscriber),
        ]);
    }

    #[Route('/notifications/unread-count', name: 'alchemy_notifier_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $subscriber = $this->currentSubscriber->getSubscriber();

        return new JsonResponse(['unreadCount' => $this->repository->countUnread($subscriber)]);
    }

    #[Route('/notifications/{id}/read', name: 'alchemy_notifier_read', methods: ['POST'])]
    public function markRead(string $id): JsonResponse
    {
        $subscriber = $this->currentSubscriber->getSubscriber();

        $notification = $this->repository->find($id);
        if (null === $notification) {
            throw new NotFoundHttpException();
        }
        if ($notification->getSubscriber()->getId() !== $subscriber->getId()) {
            throw new AccessDeniedHttpException();
        }

        $notification->markAsRead();
        $this->em->flush();

        return new JsonResponse($this->normalize($notification));
    }

    #[Route('/notifications/read-all', name: 'alchemy_notifier_read_all', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $subscriber = $this->currentSubscriber->getSubscriber();
        $count = $this->repository->markAllAsRead($subscriber);

        return new JsonResponse(['markedAsRead' => $count]);
    }

    private function normalize(Notification $notification): array
    {
        $payload = $notification->getPayload();

        // Rendered lazily, at read time, from the stored topic + payload.
        $rendered = $this->renderer->render(
            $notification->getTopic(),
            ChannelType::InApp,
            $payload,
        );

        // Main click-through target: an explicit `uri` template block, or the
        // `url` carried by the payload. Handled client-side by the uriHandler.
        $uri = $rendered?->uri ?? ($payload['url'] ?? null);

        return [
            'id' => $notification->getId(),
            'topic' => $notification->getTopic(),
            'subject' => $rendered?->subject,
            'content' => $rendered?->body,
            'data' => ['uri' => $uri] + $payload,
            'read' => $notification->isRead(),
            'readAt' => $notification->getReadAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
