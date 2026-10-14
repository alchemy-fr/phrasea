<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Consumer;

use Alchemy\WebhookBundle\Config\EntityRegistry;
use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Alchemy\WebhookBundle\Webhook\ObjectNormalizer;
use Alchemy\WebhookBundle\Webhook\WebhookTrigger;
use Doctrine\ORM\EntityManagerInterface;
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
        $data = $message->getData();
        $config = $this->entityRegistry->getConfigNode($entityClass);
        $groups = $config['groups'];
        $normalizedData = $this->getNormalizedData($entityClass, $data, $groups);

        if (null !== $changeSet = $message->getChangeSet()) {
            foreach ($changeSet as $field => $values) {
                $data[$field] = $values[0];
            }
            $before = $this->getNormalizedData($entityClass, $data, $groups);

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

    /**
     * @param array $data database values, converted only once the unit of work is cleared:
     *                    clear() detaches the association references, which createEntity() would then ignore
     */
    private function getNormalizedData(string $entityClass, array $data, array $groups): array
    {
        $uow = $this->em->getUnitOfWork();
        $uow->clear();
        $data = $this->entitySerializer->convertToPhpValue($entityClass, $data);
        $entity = $uow->createEntity($entityClass, $data);

        return $this->objectNormalizer->normalize($entity, $groups);
    }
}
