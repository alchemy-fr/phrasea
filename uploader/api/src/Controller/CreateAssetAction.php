<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\AuthBundle\Model\RemoteUser;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\Entity\Asset;
use App\Entity\Target;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    public function __construct(private readonly FileStorageManager $storageManager, private readonly AssetManager $assetManager, private readonly UploadManager $uploadManager, private readonly PathGenerator $pathGenerator, private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(Request $request): Asset
    {
        $targetId = null;
        if (!empty($targetSlug = $request->request->get('targetSlug'))) {
            $target = $this->em->getRepository(Target::class)->findOneBy([
                'slug' => $targetSlug,
            ]);
        } elseif (!empty($targetId = $request->request->get('targetId'))) {
            $target = $this->em->find(Target::class, $targetId);
        } else {
            throw new BadRequestHttpException('"targetId" or "targetSlug" is required');
        }

        if (!$target instanceof Target) {
            throw new BadRequestHttpException(sprintf('Target "%s" does not exist', $targetId));
        }

        if ($request->request->all('multipart')) {
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
            $user->getId(),
            $request->request->get('data')
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
            $user->getId(),
            $request->request->get('data')
        );
    }
}
