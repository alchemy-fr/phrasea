<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Service\Workspace\LogoManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteWorkspaceLogoAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LogoManager $logoManager,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $workspace = $this->em->find(Workspace::class, $id);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found', $id));
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);

        $this->logoManager->removeLogo($workspace);
        $this->em->persist($workspace);
        $this->em->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
