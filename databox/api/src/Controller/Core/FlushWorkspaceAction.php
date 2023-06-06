<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use App\Workspace\WorkspaceDuplicateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FlushWorkspaceAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly WorkspaceDuplicateManager $workspaceManager)
    {
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

            $this->em->remove($workspace);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $exception) {
            $this->em->rollback();
            throw $exception;
        }

        return $newWorkspace;
    }
}
