<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use App\Security\AssetUrlGenerator;
use App\Security\Authentication\JWTManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    private AssetUrlGenerator $assetUrlGenerator;
    protected UrlGeneratorInterface $urlGenerator;
    private Packages $packages;
    protected JWTManager $JWTManager;

    /**
     * @required
     */
    public function setAssetUrlGenerator(AssetUrlGenerator $assetUrlGenerator): void
    {
        $this->assetUrlGenerator = $assetUrlGenerator;
    }

    /**
     * @required
     */
    public function setPackages(Packages $packages): void
    {
        $this->packages = $packages;
    }

    /**
     * @required
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): void
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @required
     */
    public function setJWTManager(JWTManager $JWTManager): void
    {
        $this->JWTManager = $JWTManager;
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateAssetUrlOrVideoPreviewUrl(MediaInterface $media): string
    {
        if (0 === strpos($media->getMimeType(), 'video/')) {
            return $this->packages->getUrl('/images/player.webp', 'assets');
        } elseif (0 === strpos($media->getMimeType(), 'application/pdf')) {
            return $this->packages->getUrl('/images/pdf-icon.jpg', 'assets');
        } elseif (0 === strpos($media->getMimeType(), 'image/')) {
            return $this->generateAssetUrl($media);
        }

        return $this->packages->getUrl('/images/asset.jpg', 'assets');
    }

    protected function generateDownloadAssetTrackerUrl(Publication $publication, Asset $asset): string
    {
        $uri = $this->urlGenerator->generate('download_asset', [
            'publicationId' => $publication->getId(),
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->JWTManager->signUri($uri);
    }

    protected function generateDownloadSubDefTrackerUrl(Publication $publication, SubDefinition $subDefinition): string
    {
        $uri = $this->urlGenerator->generate('download_subdef', [
            'publicationId' => $publication->getId(),
            'subDefId' => $subDefinition->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->JWTManager->signUri($uri);
    }

    protected function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->assetUrlGenerator->generateAssetUrl($media, $download);
    }

    protected function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->assetUrlGenerator->generateSubDefinitionUrl($subDefinition, $download);
    }

    protected function getDownloadViaEmailUrl(PublicationAsset $publicationAsset, ?string $subDefId = null): string
    {
        if (null !== $subDefId) {
            $uri = $this->urlGenerator->generate('download_subdef_request_create', [
                'publicationId' => $publicationAsset->getPublication()->getId(),
                'subDefId' => $subDefId,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            return $this->JWTManager->signUri($uri);
        }

        $uri = $this->urlGenerator->generate('download_asset_request_create', [
            'publicationId' => $publicationAsset->getPublication()->getId(),
            'assetId' => $publicationAsset->getAsset()->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->JWTManager->signUri($uri);
    }
}
