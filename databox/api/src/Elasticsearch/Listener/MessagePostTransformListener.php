<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use App\Asset\Attribute\AttributesResolver;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\AssetPermissionComputer;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Discussion\Message;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
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
