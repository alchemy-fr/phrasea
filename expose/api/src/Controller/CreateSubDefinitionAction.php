<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\SubDefinition;
use App\Security\Voter\PublicationVoter;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreateSubDefinitionAction extends AbstractController
{
    /**
     * @var FileStorageManager
     */
    private $storageManager;

    /**
     * @var AssetManager
     */
    private $assetManager;

    public function __construct(
        FileStorageManager $storageManager,
        AssetManager $assetManager
    ) {
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
    }

    public function __invoke(Request $request): SubDefinition
    {
        $this->denyAccessUnlessGranted(PublicationVoter::PUBLISH);
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
        $this->denyAccessUnlessGranted(PublicationVoter::PUBLISH, $asset);

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
        $path = $this->storageManager->generatePath($extension);

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $this->storageManager->storeStream($path, $stream);
        fclose($stream);

        $subDefinition = $this->assetManager->createSubDefinition(
            $name,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getSize(),
            $asset,
            $request->request->all()
        );

        return $subDefinition;
    }

    private function findAsset(string $id): Asset
    {
        return $this->assetManager->findAsset($id);
    }
}
