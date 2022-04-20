<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SerializeObjectHandler extends AbstractEntityManagerHandler
{
    private const EVENT = 'webhook_serialize_update';

    private NormalizerInterface $normalizer;
    private EventProducer $eventProducer;
    private EntitySerializer $entitySerializer;

    public function __construct(NormalizerInterface $normalizer, EventProducer $eventProducer, EntitySerializer $entitySerializer)
    {
        $this->normalizer = $normalizer;
        $this->eventProducer = $eventProducer;
        $this->entitySerializer = $entitySerializer;
    }

    public function handle(EventMessage $message): void
    {
        $p = $message->getPayload();
        $event = $p['event'];
        $entityClass = $p['class'];
        $changeSet = $p['change_set'];
        $data = $this->entitySerializer->convertToPhpValue($entityClass, $p['data']);
        $changeSet = $this->entitySerializer->convertChangeSetToPhpValue($entityClass, $changeSet);

        $unitOfWork = $this->getEntityManager()->getUnitOfWork();
        $entityAfter = $unitOfWork->createEntity($entityClass, $data);
        $after = $this->normalizer->normalize($entityAfter, 'json', [
            'groups' => 'Webhook',
        ]);

        foreach ($changeSet as $field => $values) {
            $data[$field] = $values[0];
        }
        $unitOfWork->clear($entityClass);
        $entityBefore = $unitOfWork->createEntity($entityClass, $data);
        $before = $this->normalizer->normalize($entityBefore, 'json', [
            'groups' => 'Webhook',
        ]);

        $this->eventProducer->publish(WebhookHandler::createEvent($event, [
            'before' => $before,
            'after' => $after,
        ]));

        throw new \InvalidArgumentException(sprintf('OK'));
    }

    public static function createEvent(string $class, string $event, array $data, ?array $changeSet = null): EventMessage
    {
        $payload = [
            'event' => $event,
            'data' => $data,
            'class' => $class,
        ];
        if (null !== $changeSet) {
            $payload['change_set'] = $changeSet;
        }

        return new EventMessage(self::EVENT, $payload);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
