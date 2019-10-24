<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/assets", name="asset_")
 */
final class ReadAssetAction extends AbstractController
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

    /**
     * @Route("/{id}/open", name="open")
     */
    public function readAsset(string $id): Response
    {
        $asset = $this->assetManager->findAsset($id);
        $stream = $this->storageManager->getStream($asset->getPath());
        fclose($stream);

        $stream = $this->storageManager->getStream($asset->getPath());

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $asset->getMimeType(),
        ]);
    }

    /**
     * @Route("/{id}/download", name="download")
     */
    public function downloadAsset(string $id): Response
    {
        $asset = $this->assetManager->findAsset($id);
        $stream = $this->storageManager->getStream($asset->getPath());
        fclose($stream);

        $stream = $this->storageManager->getStream($asset->getPath());

        return new StreamedResponse(function () use ($stream) {
            ob_end_flush();
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $asset->getMimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $asset->getOriginalName()),
            'Content-Size' => $asset->getSize(),
        ]);
    }
}
