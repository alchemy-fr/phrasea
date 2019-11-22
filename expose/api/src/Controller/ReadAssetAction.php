<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportClient;
use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Security\Voter\PublicationAssetVoter;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use Mimey\MimeTypes;
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
    /**
     * @var ReportClient
     */
    private $reportClient;

    public function __construct(
        FileStorageManager $storageManager,
        AssetManager $assetManager,
        ReportClient $reportClient
    ) {
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
        $this->reportClient = $reportClient;
    }

    /**
     * @Route("/{id}/preview", name="preview")
     */
    public function assetPreview(string $id): Response
    {
        $asset = $this->getAssetFromPublicationAsset($id);

        return $this->getMediaStream($asset->getPreviewDefinition() ?? $asset);
    }

    /**
     * @Route("/{id}/thumbnail", name="thumbnail")
     */
    public function assetThumbnail(string $id): Response
    {
        $asset = $this->getAssetFromPublicationAsset($id);

        return $this->getMediaStream($asset->getThumbnailDefinition() ?? $asset);
    }

    private function getMediaStream(MediaInterface $media): StreamedResponse
    {
        $stream = $this->storageManager->getStream($media->getPath());

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->getMimeType(),
        ]);
    }

    private function getAssetFromPublicationAsset(string $id): Asset
    {
        $publicationAsset = $this->assetManager->findPublicationAsset($id);
        $this->denyAccessUnlessGranted(PublicationAssetVoter::READ, $publicationAsset);
        $asset = $publicationAsset->getAsset();

        return $asset;
    }

    /**
     * @Route("/{id}/sub-definitions/{type}", name="subdef_open")
     */
    public function subDefinitionOpen(string $id, string $type): Response
    {
        $asset = $this->getAssetFromPublicationAsset($id);
        $subDefinition = $this->assetManager->findAssetSubDefinition($asset, $type);
        $stream = $this->storageManager->getStream($subDefinition->getPath());

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $subDefinition->getMimeType(),
        ]);
    }

    /**
     * @Route("/{id}/sub-definitions/{type}/download", name="subdef_download")
     */
    public function subDefinitionDownload(string $id, string $type): Response
    {
        $asset = $this->getAssetFromPublicationAsset($id);
        $subDefinition = $this->assetManager->findAssetSubDefinition($asset, $type);
        $stream = $this->storageManager->getStream($subDefinition->getPath());

        $mimes = new MimeTypes();
        $extension = $mimes->getExtension($subDefinition->getMimeType());

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $subDefinition->getMimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $subDefinition->getName().'.'.$extension),
            'Content-Size' => $subDefinition->getSize(),
        ]);
    }

    /**
     * @Route("/{id}/download", name="download")
     */
    public function downloadAsset(string $id): Response
    {
        $asset = $this->getAssetFromPublicationAsset($id);
        $stream = $this->storageManager->getStream($asset->getPath());
        fclose($stream);

        $this->reportClient->pushAction('download_asset', [
            'id' => $asset->getAssetId(),
        ]);

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
