<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Storage\AssetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mime\MimeTypes;

final class CreateAssetAction extends AbstractController
{
    public function __construct(
        private readonly FileStorageManager $storageManager,
        private readonly AssetManager $assetManager,
        private readonly UploadManager $uploadManager,
        private readonly PathGenerator $pathGenerator,
    ) {
    }

    public function __invoke(Request $request): Asset
    {
        $publication = $this->getPublication($request);

        if ($request->request->has('multipart')) {
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
        } elseif ($request->request->has('upload')) {
            $upload = $request->request->all('upload');

            $originalFilename = $upload['name'] ?? null;
            $contentType = $upload['type'] ?? null;
            if (null === $contentType && !empty($originalFilename)) {
                $extension = pathinfo((string) $originalFilename, PATHINFO_EXTENSION);
                $contentType = (new MimeTypes())->getMimeTypes($extension)[0];
            }

            $contentType ??= 'application/octet-stream';

            if (null === $originalFilename) {
                $extension = (new MimeTypes())->getExtensions($contentType)[0];
            } else {
                $extension = pathinfo((string) $originalFilename, PATHINFO_EXTENSION);
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
        }
        throw new BadRequestHttpException('Missing file or contentType');

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
        $multipartUpload = $this->uploadManager->handleMultipartUpload(MultipartUploadInput::fromRequest($request));

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
