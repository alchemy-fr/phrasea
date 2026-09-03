<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use App\Service\Workspace\TermsManager;
use App\Service\Workspace\TermsPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class WorkspaceTermsPdfAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TermsManager $termsManager,
        private readonly TermsPdfGenerator $termsPdfGenerator,
    ) {
    }

    #[Route(path: '/workspaces/{id}/terms.pdf', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $workspace = $this->em->find(Workspace::class, $id);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found', $id));
        }

        $this->denyAccessUnlessGranted(WorkspaceVoter::READ_NO_TERMS, $workspace);

        $terms = $this->termsManager->getCurrentTerms($workspace);
        if (null === $terms) {
            throw new NotFoundHttpException('Workspace has no Terms & Conditions');
        }

        $pdf = $this->termsManager->getPdfContent($terms) ?? $this->termsPdfGenerator->generatePdf($terms);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="terms-%s-v%d.pdf"', $workspace->getSlug(), $terms->getVersion()),
        ]);
    }
}
