<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Asset;
use App\Security\Voter\PublicationVoter;
use App\Storage\SubDefinitionManager;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\UploadManager;
use Mimey\MimeTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    private FileStorageManager $storageManager;
    private SubDefinitionManager $assetManager;
    private UploadManager $uploadManager;
    private PathGenerator $pathGenerator;

    public function __construct(
        FileStorageManager $storageManager,
        SubDefinitionManager $assetManager,
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
        if (!$request->request->get('publication_id')) {
            // If no publication is assigned, we validate the following grant:
            $this->denyAccessUnlessGranted(PublicationVoter::CREATE);
        }

        if (null !== $request->request->get('multipart')) {
            return $this->handleMultipartUpload($request);
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

    private function handleMultipartUpload(Request $request): Asset
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        return $this->assetManager->createAsset(
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $request->request->all(),
        );
    }
}
