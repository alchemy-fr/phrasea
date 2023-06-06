<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\Workflow\WorkflowOrchestrator;
use App\Entity\Core\Asset;
use App\Entity\Workflow\WorkflowState;
use App\Security\Voter\AbstractVoter;
use App\Workflow\Event\AssetIngestWorkflowEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TriggerAssetWorkflowAction extends AbstractController
{
    public function __construct(
        private readonly WorkflowOrchestrator $workflowOrchestrator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(string $id, Request $request)
    {
        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $asset);

        $this->workflowOrchestrator->dispatchEvent(
            AssetIngestWorkflowEvent::createEvent($asset->getId(), $asset->getWorkspaceId()),
            [
                WorkflowState::INITIATOR_ID => $this->getUser()->getId(),
            ]
        );

        return new Response();
    }
}
