<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Asset;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\Entity\Target;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    private FileStorageManager $storageManager;
    private AssetManager $assetManager;
    private UploadManager $uploadManager;
    private PathGenerator $pathGenerator;
    private EntityManagerInterface $em;

    public function __construct(
        FileStorageManager $storageManager,
        AssetManager $assetManager,
        UploadManager $uploadManager,
        PathGenerator $pathGenerator,
        EntityManagerInterface $em
    ) {
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
        $this->uploadManager = $uploadManager;
        $this->pathGenerator = $pathGenerator;
        $this->em = $em;
    }

    public function __invoke(Request $request): Asset
    {
        if (empty($targetId = $request->request->get('targetId'))) {
            throw new BadRequestHttpException('"targetId" is required');
        }
        $target = $this->em->find(Target::class, $targetId);
        if (!$target instanceof Target) {
            throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $targetId));
        }

        if ($request->request->get('multipart')) {
            return $this->handleMultipartUpload($request, $target);
        }

        ini_set('max_execution_time', '600');

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            throw new BadRequestHttpException('"file" is required');
        }
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        $extension = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION);
        $path = $this->pathGenerator->generatePath($extension);

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $this->storageManager->storeStream($path, $stream);
        fclose($stream);

        /** @var RemoteUser $user */
        $user = $this->getUser();

        return $this->assetManager->createAsset(
            $target,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getSize(),
            $user->getId()
        );
    }

    private function handleMultipartUpload(Request $request, Target $target): Asset
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        /** @var RemoteUser $user */
        $user = $this->getUser();

        return $this->assetManager->createAsset(
            $target,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $user->getId()
        );
    }
}
