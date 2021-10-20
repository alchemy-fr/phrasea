<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Asset;
use App\Entity\SubDefinition;
use App\Security\Voter\AssetVoter;
use App\Storage\AssetManager;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\UploadManager;
use Mimey\MimeTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreateSubDefinitionAction extends AbstractController
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

    public function __invoke(Request $request): SubDefinition
    {
        $assetId = $request->request->get('asset_id');
        if (!$assetId) {
            throw new BadRequestHttpException('"asset_id" is required');
        }

        $name = $request->request->get('name');
        if (empty($name)) {
            throw new BadRequestHttpException('"name" is required and must not be empty');
        }

        $asset = $this->findAsset($assetId);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException(sprintf('Asset %s not found', $assetId));
        }
        $this->denyAccessUnlessGranted(AssetVoter::EDIT, $asset);

        if (null !== $request->request->get('multipart')) {
            return $this->handleMultipartUpload($request, $asset, $name);
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

            return $this->assetManager->createSubDefinition(
                $name,
                $path,
                $uploadedFile->getMimeType(),
                $uploadedFile->getSize(),
                $asset,
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

            $subDefinition = $this->assetManager->createSubDefinition(
                $name,
                $path,
                $contentType,
                (int) ($upload['size'] ?? 0),
                $asset,
                $request->request->all()
            );

            $url = $this->uploadManager->createPutObjectSignedURL($path, $contentType);
            $subDefinition->setUploadURL($url);

            return $subDefinition;
        } else {
            throw new BadRequestHttpException('Missing file or contentType');
        }
    }

    private function handleMultipartUpload(Request $request, Asset $asset, string $name): SubDefinition
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        return $this->assetManager->createSubDefinition(
            $name,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getSize(),
            $asset,
            $request->request->all()
        );
    }

    private function findAsset(string $id): Asset
    {
        return $this->assetManager->findAsset($id);
    }
}
