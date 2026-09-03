<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Entity\Core\Workspace;
use App\Security\Voter\WorkspaceVoter;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SignWorkspaceTermsAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TermsManager $termsManager,
    ) {
    }

    public function __invoke(string $id): Workspace
    {
        $workspace = $this->em->find(Workspace::class, $id);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found', $id));
        }

        $this->denyAccessUnlessGranted(WorkspaceVoter::READ_NO_TERMS, $workspace);

        $user = $this->getUser();
        if (!$user instanceof JwtUser) {
            throw $this->createAccessDeniedException();
        }

        $terms = $this->termsManager->getCurrentTerms($workspace);
        if (null === $terms) {
            throw new BadRequestHttpException('Workspace has no Terms & Conditions to sign');
        }

        $this->termsManager->sign($terms, $user->getId());

        return $workspace;
    }
}
