<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
use App\Security\AssetUrlGenerator;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    private AssetUrlGenerator $assetUrlGenerator;
    protected UrlGeneratorInterface $urlGenerator;
    private Packages $packages;

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

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->assetUrlGenerator->generateAssetUrl($media, $download);
    }

    /**
     * @param PublicationAsset|Asset $publicationAsset
     */
    protected function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->assetUrlGenerator->generateSubDefinitionUrl($subDefinition, $download);
    }
}
