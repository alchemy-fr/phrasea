<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Config\EntityRegistry;
use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Alchemy\WebhookBundle\Webhook\ObjectNormalizer;
use Alchemy\WebhookBundle\Webhook\WebhookTrigger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SerializeObjectHandler
{
    public function __construct(
        private EntitySerializer $entitySerializer,
        private EntityRegistry $entityRegistry,
        private WebhookTrigger $webhookTrigger,
        private ObjectNormalizer $objectNormalizer,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SerializeObject $message): void
    {
        $event = $message->getEvent();
        if (empty($this->webhookTrigger->getWebhooksForEvent($event))) {
            return;
        }

        $entityClass = $message->getClass();
        $data = $this->entitySerializer->convertToPhpValue($entityClass, $message->getData());
        $config = $this->entityRegistry->getConfigNode($entityClass);
        $groups = $config['groups'];
        $meta = $this->em->getClassMetadata($entityClass);
        $normalizedData = $this->getNormalizedData($meta, $data, $groups);

        if (null !== $changeSet = $message->getChangeSet()) {
            $changeSet = $this->entitySerializer->convertChangeSetToPhpValue($entityClass, $changeSet);
            foreach ($changeSet as $field => $values) {
                $data[$field] = $values[0];
            }
            $before = $this->getNormalizedData($meta, $data, $groups);

            $this->webhookTrigger->triggerEvent($event, [
                'before' => $before,
                'after' => $normalizedData,
                'change_set' => $message->getChangeSet() ?? [],
            ]);
        } else {
            $this->webhookTrigger->triggerEvent($event, [
                'data' => $normalizedData,
            ]);
        }
    }

    private function getNormalizedData(ClassMetadata $meta, array $data, array $groups): array
    {
        $uow = $this->em->getUnitOfWork();
        $uow->clear($meta->name);
        $entity = $uow->createEntity($meta->name, $data);

        return $this->objectNormalizer->normalize($entity, $groups);
    }
}
