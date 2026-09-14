<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\StorageBundle\Upload\UploadManager;
use App\Controller\Traits\MultipartUploadResolverTrait;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Service\Storage\FileManager;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UploadWorkspaceTermsPdfAction extends AbstractController
{
    use MultipartUploadResolverTrait;

    private const int MAX_SIZE = 20 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploadManager $uploadManager,
        private readonly FileManager $fileManager,
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

        $upload = $this->resolveMultipartUpload($request, $this->em, $this->uploadManager);

        if ('application/pdf' !== $upload->getType()) {
            throw new BadRequestHttpException(sprintf('Terms file must be a PDF, got "%s"', $upload->getType()));
        }
        if ($upload->getSize() > self::MAX_SIZE) {
            throw new BadRequestHttpException(sprintf('Terms PDF must not exceed %d MB', self::MAX_SIZE / 1024 / 1024));
        }

        $file = $this->fileManager->createFileFromMultipartUpload($upload, $workspace);
        $this->termsManager->setTermsPdfFromFile($workspace, $file);
        $this->em->flush();

        return $workspace;
    }
}
