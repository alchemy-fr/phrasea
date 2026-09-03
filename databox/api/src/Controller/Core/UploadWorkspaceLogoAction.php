<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\StorageBundle\Upload\UploadManager;
use App\Controller\Traits\MultipartUploadResolverTrait;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use App\Service\Workspace\LogoManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UploadWorkspaceLogoAction extends AbstractController
{
    use MultipartUploadResolverTrait;

    private const int MAX_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploadManager $uploadManager,
        private readonly LogoManager $logoManager,
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

        if ($upload->getSize() > self::MAX_SIZE) {
            throw new BadRequestHttpException(sprintf('Logo must not exceed %d MB', self::MAX_SIZE / 1024 / 1024));
        }

        $this->logoManager->setLogo($workspace, $upload->getPath(), $upload->getType());
        $this->em->persist($workspace);
        $this->em->flush();

        return $workspace;
    }
}
