<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadAssetAction extends AbstractController
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

    public function __invoke(string $id)
    {
        $asset = $this->assetManager->findAsset($id);
        $stream = $this->storageManager->getStream($asset->getPath());

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => $asset->getMimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $asset->getOriginalName()),
            'Content-Size' => $asset->getSize(),
        ]);
    }
}
