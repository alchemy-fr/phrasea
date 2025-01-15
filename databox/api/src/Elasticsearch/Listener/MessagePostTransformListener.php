<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Entity\Discussion\Message;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class MessagePostTransformListener implements EventSubscriberInterface
{
    public function __construct(
    ) {
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Message $message */
        if (!($message = $event->getObject()) instanceof Message) {
            return;
        }

        $document = $event->getDocument();
        $document->set('users', []);
        $document->set('groups', []);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
