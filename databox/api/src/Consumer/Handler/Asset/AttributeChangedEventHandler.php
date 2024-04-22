<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Workflow\Event\AttributeUpdateWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AttributeChangedEventHandler
{
    public function __construct(
        private WorkflowOrchestrator $workflowOrchestrator,
        private EntityManagerInterface $em
    ) {
    }

    public function __invoke(AttributeChanged $message): void
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getAssetId());

        $this->workflowOrchestrator->dispatchEvent(AttributeUpdateWorkflowEvent::createEvent(
            $message->getAttributes(),
            $asset->getId(),
            $asset->getWorkspaceId(),
        ), [
            WorkflowState::INITIATOR_ID => $message->getUserId(),
        ]);
    }
}
