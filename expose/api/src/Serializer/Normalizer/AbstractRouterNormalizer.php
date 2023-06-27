<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\SubDefinition;
use App\Security\AssetUrlGenerator;
use App\Security\Authentication\JWTManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    private AssetUrlGenerator $assetUrlGenerator;
    protected UrlGeneratorInterface $urlGenerator;
    protected JWTManager $JWTManager;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setAssetUrlGenerator(AssetUrlGenerator $assetUrlGenerator): void
    {
        $this->assetUrlGenerator = $assetUrlGenerator;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): void
    {
        $this->urlGenerator = $urlGenerator;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setJWTManager(JWTManager $JWTManager): void
    {
        $this->JWTManager = $JWTManager;
    }

    protected function generateDownloadAssetTrackerUrl(Asset $asset): string
    {
        $uri = $this->urlGenerator->generate('download_asset', [
            'publicationId' => $asset->getPublication()->getId(),
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->JWTManager->signUri($uri);
    }

    protected function generateDownloadSubDefTrackerUrl(SubDefinition $subDefinition): string
    {
        $uri = $this->urlGenerator->generate('download_subdef', [
            'publicationId' => $subDefinition->getAsset()->getPublication()->getId(),
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

    protected function getDownloadViaEmailUrl(Asset $asset, string $subDefId = null): string
    {
        if (null !== $subDefId) {
            $uri = $this->urlGenerator->generate('download_subdef_request_create', [
                'publicationId' => $asset->getPublication()->getId(),
                'subDefId' => $subDefId,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            return $this->JWTManager->signUri($uri);
        }

        $uri = $this->urlGenerator->generate('download_asset_request_create', [
            'publicationId' => $asset->getPublication()->getId(),
            'assetId' => $asset->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->JWTManager->signUri($uri);
    }
}
