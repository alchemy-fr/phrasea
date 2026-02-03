<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use Alchemy\StorageBundle\Upload\UploadManager;
use App\Entity\Asset;
use App\Entity\SubDefinition;
use App\Security\Voter\AssetVoter;
use App\Storage\AssetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mime\MimeTypes;

final class CreateSubDefinitionAction extends AbstractController
{
    public function __construct(private readonly FileStorageManager $storageManager, private readonly AssetManager $assetManager, private readonly UploadManager $uploadManager, private readonly PathGenerator $pathGenerator)
    {
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
        $this->denyAccessUnlessGranted(AssetVoter::EDIT, $asset);

        if ($request->request->has('multipart')) {
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
                $asset,
                $name,
                $path,
                $uploadedFile->getMimeType(),
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

            $subDefinition = $this->assetManager->createSubDefinition(
                $asset,
                $name,
                $path,
                $contentType,
                (int) ($upload['size'] ?? 0),
                $request->request->all()
            );

            $url = $this->uploadManager->createPutObjectSignedURL($path, $contentType);
            $subDefinition->setUploadURL($url);

            return $subDefinition;
        }
        throw new BadRequestHttpException('Missing file or contentType');

    }

    private function handleMultipartUpload(Request $request, Asset $asset, string $name): SubDefinition
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload(MultipartUploadInput::fromRequest($request));

        return $this->assetManager->createSubDefinition(
            $asset,
            $name,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getSize(),
            $request->request->all()
        );
    }

    private function findAsset(string $id): Asset
    {
        return $this->assetManager->findAsset($id);
    }
}
