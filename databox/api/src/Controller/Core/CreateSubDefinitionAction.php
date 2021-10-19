<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Core\Asset;
use App\Entity\Core\SubDefinition;
use App\Entity\Core\SubDefinitionSpec;
use App\Entity\Core\Workspace;
use App\Storage\SubDefinitionManager;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\UploadManager;
use Mimey\MimeTypes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateSubDefinitionAction extends AbstractController
{
    private FileStorageManager $storageManager;
    private SubDefinitionManager $subDefinitionManager;
    private UploadManager $uploadManager;
    private PathGenerator $pathGenerator;

    public function __construct(
        FileStorageManager $storageManager,
        SubDefinitionManager $subDefinitionManager,
        UploadManager $uploadManager,
        PathGenerator $pathGenerator
    ) {
        $this->storageManager = $storageManager;
        $this->subDefinitionManager = $subDefinitionManager;
        $this->uploadManager = $uploadManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function __invoke(Request $request): SubDefinition
    {
        $asset = $this->resolveAsset($request);
        $subDefSpec = $this->resolveSubDefSpec($asset->getWorkspace(), $request);

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

            return $this->subDefinitionManager->createSubDefinition(
                $asset,
                $subDefSpec,
                $path,
                $uploadedFile->getMimeType(),
                $uploadedFile->getSize()
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

            $subDef = $this->subDefinitionManager->createSubDefinition(
                $asset,
                $subDefSpec,
                $path,
                $contentType,
                (int) ($upload['size'] ?? 0)
            );

            $url = $this->uploadManager->createPutObjectSignedURL($path, $contentType);
            $subDef->setUploadURL($url);

            return $asset;
        } else {
            throw new BadRequestHttpException('Missing file or contentType');
        }
    }

    private function handleMultipartUpload(Request $request): SubDefinition
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        return $this->subDefinitionManager->createSubDefinition(
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            $multipartUpload->getFilename(),
            $multipartUpload->getSize(),
            $request->request->all(),
        );
    }

    private function resolveAsset(Request $request): Asset
    {
        if ($assetId = $request->request->get('assetId')) {
            $asset = $this->subDefinitionManager->getAssetFromId($assetId);
        }

        if (empty($assetId)) {
            throw new BadRequestHttpException('Missing assetId');
        }

        if (!$asset instanceof Asset) {
            throw new BadRequestHttpException('Asset not found');
        }

        return $asset;
    }

    private function resolveSubDefSpec(Workspace $workspace, Request $request): SubDefinitionSpec
    {
        if ($name = $request->request->get('name')) {
            $spec = $this->subDefinitionManager->getSpecFromName($workspace, $name);
        } elseif ($id = $request->request->get('specId')) {
            $spec = $this->subDefinitionManager->getSpecFromId($workspace, $id);
        } else {
            throw new BadRequestHttpException('Missing spec "name" id "specId"');
        }

        if (!$spec instanceof SubDefinitionSpec) {
            throw new BadRequestHttpException('SubDefinitionSpec not found');
        }

        return $spec;
    }
}
