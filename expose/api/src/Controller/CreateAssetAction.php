<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\Asset;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    private $validator;
    private $resourceMetadataFactory;

    /**
     * @var FileStorageManager
     */
    private $storageManager;

    /**
     * @var AssetManager
     */
    private $assetManager;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        FileStorageManager $storageManager,
        AssetManager $assetManager
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
    }

    public function __invoke(Request $request): Asset
    {
        $publicationId = $request->request->get('publication_id');
        if (!$publicationId) {
            throw new BadRequestHttpException('Missing publication_id');
        }
        $assetId = $request->request->get('asset_id');
        if (!$publicationId) {
            throw new BadRequestHttpException('Missing asset_id');
        }

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

        $asset = $this->assetManager->createAsset(
            $publicationId,
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getSize(),
            $request->request->all()
        );

        return $asset;
    }
}
