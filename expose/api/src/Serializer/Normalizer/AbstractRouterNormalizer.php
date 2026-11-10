<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Alchemy\AuthBundle\Security\UriJwtManager;
use App\Entity\Asset;
use App\Entity\MediaInterface;
use App\Entity\Publication;
use App\Entity\SubDefinition;
use App\Security\AssetUrlGenerator;
use App\Security\DownloadMode;
use App\Security\PublicationDownloadPolicy;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractRouterNormalizer implements EntityNormalizerInterface
{
    private AssetUrlGenerator $assetUrlGenerator;
    protected UrlGeneratorInterface $urlGenerator;
    protected UriJwtManager $uriJwtManager;
    protected PublicationDownloadPolicy $downloadPolicy;

    #[Required]
    public function setAssetUrlGenerator(AssetUrlGenerator $assetUrlGenerator): void
    {
        $this->assetUrlGenerator = $assetUrlGenerator;
    }

    #[Required]
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): void
    {
        $this->urlGenerator = $urlGenerator;
    }

    #[Required]
    public function setUriJwtManager(UriJwtManager $uriJwtManager): void
    {
        $this->uriJwtManager = $uriJwtManager;
    }

    #[Required]
    public function setDownloadPolicy(PublicationDownloadPolicy $downloadPolicy): void
    {
        $this->downloadPolicy = $downloadPolicy;
    }

    protected function getDownloadMode(Publication $publication): DownloadMode
    {
        return $this->downloadPolicy->getMode($publication);
    }

    protected function generateAssetDownloadUrl(Asset $asset): ?string
    {
        $parameters = [
            'publicationId' => $asset->getPublication()->getId(),
            'assetId' => $asset->getId(),
        ];

        return $this->generateDownloadUrl($asset->getPublication(), 'download_asset', 'download_asset_request_create', $parameters);
    }

    protected function generateSubDefinitionDownloadUrl(SubDefinition $subDefinition): ?string
    {
        $parameters = [
            'publicationId' => $subDefinition->getAsset()->getPublication()->getId(),
            'subDefId' => $subDefinition->getId(),
        ];

        return $this->generateDownloadUrl($subDefinition->getAsset()->getPublication(), 'download_subdef', 'download_subdef_request_create', $parameters);
    }

    protected function generateArchiveDownloadUrl(Publication $publication): ?string
    {
        return $this->generateDownloadUrl($publication, 'archive_download', 'archive_download_request_create', [
            'id' => $publication->getId(),
        ]);
    }

    /**
     * The URL a download button of the client points to, as the reader may download:
     * the email request of a publication downloaded via email (for its editors too, the client
     * asks for the email then), the direct download otherwise, signed with the `dl` claim
     * only when the reader may download right away (no download terms left to accept).
     */
    private function generateDownloadUrl(Publication $publication, string $route, string $emailRequestRoute, array $parameters): ?string
    {
        $mode = $this->getDownloadMode($publication);
        if (DownloadMode::None === $mode) {
            return null;
        }

        if ($publication->isDownloadViaEmail()) {
            return $this->uriJwtManager->signUri($this->urlGenerator->generate($emailRequestRoute, $parameters, UrlGeneratorInterface::ABSOLUTE_URL));
        }

        $uri = $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);

        return $mode->exposesFiles()
            ? $this->downloadPolicy->signDirectDownloadUri($uri)
            : $this->uriJwtManager->signUri($uri);
    }

    protected function generateAssetUrl(MediaInterface $media, bool $download = false): string
    {
        return $this->assetUrlGenerator->generateAssetUrl($media, $download);
    }

    protected function generateSubDefinitionUrl(SubDefinition $subDefinition, bool $download = false): string
    {
        return $this->assetUrlGenerator->generateSubDefinitionUrl($subDefinition, $download);
    }
}
