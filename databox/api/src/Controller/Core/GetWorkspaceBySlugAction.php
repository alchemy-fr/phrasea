<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetWorkspaceBySlugAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
    }

    public function __invoke(string $slug)
    {
        $workspace = $this->em->getRepository(Workspace::class)
        ->findOneBy([
            'slug' => $slug,
        ]);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace with slug "%s" not found', $slug));
        }

        $this->denyAccessUnlessGranted(WorkspaceVoter::READ, $workspace);

        return $workspace;
    }
}
