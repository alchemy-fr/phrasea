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
            if (!$downloadViaEmail) {
                $object->setDownloadUrl($this
                    ->generateDownloadAssetTrackerUrl($publicationAsset->getPublication(), $publicationAsset->getAsset()));
            } else {
                $object->setDownloadUrl($this->getDownloadViaEmailUrl($publicationAsset));
            }
        }

        $object->setUrl($this->generateAssetUrl($object->getPreviewDefinition() ?? $object));
        $object->setThumbUrl($this->generateAssetUrlOrVideoPreviewUrl($object->getThumbnailDefinition() ?? $object));

        if (!empty($webVTT = $object->getWebVTT())) {
            $object->setWebVTTLink($this->urlGenerator->generate('asset_webvtt', [
                'id' => $object->getId(),
                'hash' => md5($webVTT),
            ], UrlGeneratorInterface::ABSOLUTE_URL));
        }
    }

    public function support($object): bool
    {
        return $object instanceof Asset;
    }
}
