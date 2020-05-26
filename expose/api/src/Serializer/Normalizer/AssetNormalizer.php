<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\PublicationAsset;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AssetNormalizer extends AbstractRouterNormalizer
{
    /**
     * @param Asset $object
     */
    public function normalize($object, array &$context = []): void
    {
        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $context['publication_asset'] ?? null;
        $downloadViaEmail = $context['download_via_email'] ?? false;

        if ($publicationAsset instanceof PublicationAsset) {
            $asset = $publicationAsset->getAsset();

            $object->setUrl($this->generateAssetUrl($asset->getPreviewDefinition() ?? $asset));
            $object->setThumbUrl($this->generateAssetUrl($asset->getThumbnailDefinition() ?? $asset));
            if (!$downloadViaEmail) {
                $object->setDownloadUrl($this->generateAssetUrl($asset, true));
            } else {
                $object->setDownloadUrl($this->getDownloadViaEmailUrl($publicationAsset));
            }
        } else {
            $object->setUrl($this->generateAssetUrl($object->getPreviewDefinition() ?? $object));
            $object->setThumbUrl($this->generateAssetUrl($object->getThumbnailDefinition() ?? $object));
            if (!$downloadViaEmail) {
                $object->setDownloadUrl($this->generateAssetUrl($object, true));
            }
        }
    }

    private function getDownloadViaEmailUrl(PublicationAsset $publicationAsset): string
    {
        return $this->urlGenerator->generate('download_request_create', [
            'id' => $publicationAsset->getPublication()->getId(),
            'assetId' => $publicationAsset->getAsset()->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
