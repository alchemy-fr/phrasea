<?php

declare(strict_types=1);

namespace App\Controller\Core;

use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Security\Voter\RenditionClassVoter;
use App\Storage\RenditionManager;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateRenditionAction extends AbstractController
{
    private FileStorageManager $storageManager;
    private RenditionManager $renditionManager;
    private UploadManager $uploadManager;
    private PathGenerator $pathGenerator;

    public function __construct(
        FileStorageManager $storageManager,
        RenditionManager $renditionManager,
        UploadManager $uploadManager,
        PathGenerator $pathGenerator
    ) {
        $this->storageManager = $storageManager;
        $this->renditionManager = $renditionManager;
        $this->uploadManager = $uploadManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function __invoke(Request $request): AssetRendition
    {
        $asset = $this->resolveAsset($request);
        $this->checkPermission($asset);
        $definition = $this->resolveRenditionDefinition($asset->getWorkspace(), $request);

        if (null !== $request->request->get('multipart')) {
            return $this->handleMultipartUpload($asset, $definition, $request);
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

            return $this->renditionManager->createOrReplaceRendition(
                $asset,
                $definition,
                File::STORAGE_S3_MAIN,
                $path,
                $uploadedFile->getMimeType(),
                $uploadedFile->getSize()
            );
        } else {
            throw new BadRequestHttpException('Missing file or multipart');
        }
    }

    private function checkPermission(Asset $asset): void
    {
        $rendition = new AssetRendition();
        $rendition->setAsset($asset);

        $this->denyAccessUnlessGranted(RenditionClassVoter::CREATE, $rendition);
    }

    private function handleMultipartUpload(Asset $asset, RenditionDefinition $definition, Request $request): AssetRendition
    {
        $multipartUpload = $this->uploadManager->handleMultipartUpload($request);

        return $this->renditionManager->createOrReplaceRendition(
            $asset,
            $definition,
            File::STORAGE_S3_MAIN,
            $multipartUpload->getPath(),
            $multipartUpload->getType(),
            (int) ($upload['size'] ?? 0)
        );
    }

    private function resolveAsset(Request $request): Asset
    {
        $asset = null;
        if ($assetId = $request->request->get('assetId')) {
            $asset = $this->renditionManager->getAssetFromId($assetId);
        }

        if (empty($assetId)) {
            throw new BadRequestHttpException('Missing assetId');
        }

        if (!$asset instanceof Asset) {
            throw new BadRequestHttpException('Asset not found');
        }

        return $asset;
    }

    private function resolveRenditionDefinition(Workspace $workspace, Request $request): RenditionDefinition
    {
        if ($name = $request->request->get('name')) {
            $definition = $this->renditionManager->getDefinitionFromName($workspace, $name);
        } elseif ($id = $request->request->get('definitionId')) {
            $definition = $this->renditionManager->getDefinitionFromId($workspace, $id);
        } else {
            throw new BadRequestHttpException('Missing definition "name" or "definitionId"');
        }

        if (!$definition instanceof RenditionDefinition) {
            throw new BadRequestHttpException('Rendition definition not found');
        }

        return $definition;
    }
}
