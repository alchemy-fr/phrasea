<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Workflow\Event\AttributeUpdateWorkflowEvent;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

final class AttributeChangedEventHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'attributes_changed';

    public function __construct(
        private readonly WorkflowOrchestrator $workflowOrchestrator,
    ) {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $id = $payload['assetId'];

        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new ObjectNotFoundForHandlerException(Asset::class, $id, self::class);
        }

        $attributes = $payload['attributes'] ?? [];
        $this->workflowOrchestrator->dispatchEvent(AttributeUpdateWorkflowEvent::createEvent(
            $attributes,
            $asset->getId(),
            $asset->getWorkspaceId(),
        ), [
            WorkflowState::INITIATOR_ID => $payload['userId'] ?? null,
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }

    public static function createEvent(array $attributes, string $assetId, ?string $userId): EventMessage
    {
        return new EventMessage(self::EVENT, [
            'attributes' => $attributes,
            'assetId' => $assetId,
            'userId' => $userId,
        ]);
    }
}
