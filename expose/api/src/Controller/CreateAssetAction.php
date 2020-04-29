<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Security\Voter\PublicationVoter;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
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

    public function __invoke(Request $request): Asset
    {
        if (!$request->request->get('publication_id')) {
            // If no publication is assigned, we validate the following grant:
            $this->denyAccessUnlessGranted(PublicationVoter::CREATE);
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

        return $this->assetManager->createAsset(
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getSize(),
            $request->request->all()
        );
    }
}
