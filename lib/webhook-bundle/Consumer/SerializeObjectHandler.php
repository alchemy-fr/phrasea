<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Config\EntityRegistry;
use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Alchemy\WebhookBundle\Webhook\WebhookTrigger;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SerializeObjectHandler extends AbstractEntityManagerHandler
{
    private const EVENT = 'webhook_serialize_update';

    private NormalizerInterface $normalizer;
    private EntitySerializer $entitySerializer;
    private EntityRegistry $entityRegistry;
    private WebhookTrigger $webhookTrigger;

    public function __construct(
        NormalizerInterface $normalizer,
        EntitySerializer $entitySerializer,
        EntityRegistry $entityRegistry,
        WebhookTrigger $webhookTrigger
    )
    {
        $this->normalizer = $normalizer;
        $this->entitySerializer = $entitySerializer;
        $this->entityRegistry = $entityRegistry;
        $this->webhookTrigger = $webhookTrigger;
    }

    public function handle(EventMessage $message): void
    {
        $p = $message->getPayload();
        $event = $p['event'];
        $entityClass = $p['class'];
        $data = $this->entitySerializer->convertToPhpValue($entityClass, $p['data']);
        $config = $this->entityRegistry->getConfigNode($entityClass);
        $groups = $config['groups'];
        $meta = $this->getEntityManager()->getClassMetadata($entityClass);
        $normalizedData = $this->getNormalizedData($meta, $data, $groups);

        if (isset($p['change_set'])) {
            $changeSet = $p['change_set'] ? $this->entitySerializer->convertChangeSetToPhpValue($entityClass, $p['change_set']) : null;
            foreach ($changeSet as $field => $values) {
                $data[$field] = $values[0];
            }
            $before = $this->getNormalizedData($meta, $data, $groups);

            $this->webhookTrigger->triggerEvent($event, [
                'before' => $before,
                'after' => $normalizedData,
                'change_set' => $p['change_set'] ?? [],
            ]);
        } else {
            $this->webhookTrigger->triggerEvent($event, [
                'data' => $normalizedData,
            ]);
        }
    }

    private function getNormalizedData(ClassMetadata $meta, array $data, array $groups): array
    {
        $em = $this->getEntityManager();
        $uow = $em->getUnitOfWork();
        $uow->clear($meta->name);
        $entityBefore = $uow->createEntity($meta->name, $data);

        return $this->normalizer->normalize($entityBefore, 'json', [
            'groups' => $groups,
        ]);
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
