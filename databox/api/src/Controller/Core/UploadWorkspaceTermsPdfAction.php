<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UploadWorkspaceTermsPdfAction extends AbstractController
{
    private const int MAX_SIZE = 20 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TermsManager $termsManager,
    ) {
    }

    public function __invoke(string $id, Request $request): Workspace
    {
        $workspace = $this->em->find(Workspace::class, $id);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found', $id));
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile || !$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Missing or invalid multipart "file"');
        }
        if (0 === $uploadedFile->getSize() || $uploadedFile->getSize() > self::MAX_SIZE) {
            throw new BadRequestHttpException(sprintf('Terms PDF must be between 1 byte and %d MB', self::MAX_SIZE / 1024 / 1024));
        }

        $this->termsManager->setTermsPdf($workspace, file_get_contents($uploadedFile->getRealPath()));
        $this->em->flush();

        return $workspace;
    }
}
