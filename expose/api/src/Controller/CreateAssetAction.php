<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\AssetVoter;
use App\Storage\AssetManager;
use Mimey\MimeTypes;
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

    public function __construct(
        FileStorageManager $storageManager,
        AssetManager $assetManager,
        UploadManager $uploadManager,
        PathGenerator $pathGenerator
    ) {
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
        $this->uploadManager = $uploadManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function __invoke(Request $request): Asset
    {
        $publication = $this->getPublication($request);

        if (null !== $request->request->get('multipart')) {
            return $this->handleMultipartUpload($publication, $request);
        }

        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');
        if (null !== $uploadedFile) {
            ini_set('max_execution_time', '600');

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

            return $this->assetManager->createAsset(
                $publication,
                $path,
                $uploadedFile->getMimeType(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getSize(),
                $request->request->all()
            );
        } elseif (null !== $upload = $request->request->get('upload')) {
            if (!is_array($upload)) {
                throw new BadRequestHttpException('"upload" must be an array');
            }

            $originalFilename = $upload['name'] ?? null;
            $contentType = $upload['type'] ?? null;
            if (null === $contentType && !empty($originalFilename)) {
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                $contentType = (new MimeTypes())->getMimeType($extension);
            }

            $contentType ??= 'application/octet-stream';

            if (null === $originalFilename) {
                $extension = (new MimeTypes())->getExtension($contentType);
            } else {
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            }
            $path = $this->pathGenerator->generatePath($extension);

            $asset = $this->assetManager->createAsset(
                $publication,
                $path,
                $contentType,
                $originalFilename ?? 'file',
                (int) ($upload['size'] ?? 0),
                $request->request->all()
            );

            $url = $this->uploadManager->createPutObjectSignedURL($path, $contentType);
            $asset->setUploadURL($url);

            return $asset;
        } else {
            throw new BadRequestHttpException('Missing file or contentType');
        }
    }

    private function getPublication(Request $request): Publication
    {
        if (!($id = $request->request->get('publication_id'))) {
            throw new BadRequestHttpException('Missing "publication_id"');
        }

        return $this->assetManager->getPublicationWithEditGrant($id);
    }

    private function handleMultipartUpload(Publication $publication, Request $request): Asset
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        return $this->assetManager->createAsset(
            $publication,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $request->request->all(),
        );
    }
}
