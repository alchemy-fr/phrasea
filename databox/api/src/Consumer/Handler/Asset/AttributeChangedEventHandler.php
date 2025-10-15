<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Service\Workflow\Event\AttributeUpdateWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class AttributeChangedEventHandler
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AttributeChanged $message): void
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getAssetId());

        $authorId = $message->getUserId();
        $this->workflowOrchestrator->dispatchEvent(AttributeUpdateWorkflowEvent::createEvent(
            $message->getAttributes(),
            $asset->getId(),
            $asset->getWorkspaceId(),
        ), [
            WorkflowState::INITIATOR_ID => $authorId,
        ]);

        $this->bus->dispatch(new NotifyAssetTopic(
            Asset::EVENT_UPDATE,
            $asset->getId(),
            $authorId,
        ));
    }
}
