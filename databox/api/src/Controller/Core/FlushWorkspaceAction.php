<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Consumer\Handler\Workspace\DeleteWorkspaceHandler;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use App\Workspace\WorkspaceDuplicateManager;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class FlushWorkspaceAction extends AbstractController
{
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;
    private WorkspaceDuplicateManager $workspaceManager;

    public function __construct(
        EventProducer $eventProducer,
        EntityManagerInterface $em,
        WorkspaceDuplicateManager $workspaceManager
    ) {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
        $this->workspaceManager = $workspaceManager;
    }

    public function __invoke(string $id)
    {
        $workspace = $this->em->find(Workspace::class, $id);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found', $id));
        }

        $this->denyAccessUnlessGranted(WorkspaceVoter::EDIT, $workspace);

        $this->em->beginTransaction();
        try {
            $slug = $workspace->getSlug();
            $workspace->setSlug('_DEL_'.$workspace->getId());
            $this->em->persist($workspace);
            $this->em->flush();

            $newWorkspace = $this->workspaceManager->duplicateWorkspace($workspace, $slug);

            $this->em->flush();
            $this->em->commit();
        } catch (Throwable $exception) {
            $this->em->rollback();
            throw $exception;
        }

        $this->eventProducer->publish(DeleteWorkspaceHandler::createEvent($workspace->getId()));

        return $newWorkspace;
    }
}
